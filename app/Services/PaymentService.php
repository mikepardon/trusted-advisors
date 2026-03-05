<?php

namespace App\Services;

use App\Models\PaymentCustomer;
use App\Models\Purchase;
use App\Models\Unlockable;
use App\Models\User;
use App\Models\UserSubscription;
use App\Models\UserUnlockable;
use Illuminate\Support\Facades\Http;
use App\Events\PremiumStatusChanged;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session as StripeSession;
use Stripe\Customer;
use Stripe\BillingPortal\Session as PortalSession;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;

class PaymentService
{
    public function __construct()
    {
        Stripe::setApiKey(config('services.stripe.secret'));
    }

    // ── Stripe ──────────────────────────────────────────────────

    public function createStripeCheckoutSession(User $user, string $mode, ?Unlockable $unlockable = null): StripeSession
    {
        $customerId = $this->getOrCreateStripeCustomer($user);

        $params = [
            'customer' => $customerId,
            'success_url' => url('/payment/processing'),
            'cancel_url' => url('/shop'),
            'metadata' => [
                'user_id' => $user->id,
            ],
        ];

        if ($mode === 'subscription') {
            $params['mode'] = 'subscription';
            $params['line_items'] = [[
                'price' => config('services.stripe.premium_price_id'),
                'quantity' => 1,
            ]];
            $params['metadata']['type'] = 'subscription';
        } else {
            // One-time purchase of an unlockable
            $params['mode'] = 'payment';
            $params['line_items'] = [[
                'price' => $unlockable->stripe_price_id,
                'quantity' => 1,
            ]];
            $params['metadata']['type'] = 'one_time';
            $params['metadata']['unlockable_id'] = $unlockable->id;
        }

        return StripeSession::create($params);
    }

    public function getOrCreateStripeCustomer(User $user): string
    {
        $paymentCustomer = PaymentCustomer::where('user_id', $user->id)
            ->where('platform', 'stripe')
            ->first();

        if ($paymentCustomer) {
            return $paymentCustomer->platform_customer_id;
        }

        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
            'metadata' => ['user_id' => $user->id],
        ]);

        PaymentCustomer::create([
            'user_id' => $user->id,
            'platform' => 'stripe',
            'platform_customer_id' => $customer->id,
        ]);

        return $customer->id;
    }

    public function createPortalSession(User $user): PortalSession
    {
        $paymentCustomer = PaymentCustomer::where('user_id', $user->id)
            ->where('platform', 'stripe')
            ->first();

        return PortalSession::create([
            'customer' => $paymentCustomer->platform_customer_id,
            'return_url' => url('/profile'),
        ]);
    }

    public function handleStripeWebhook(string $payload, string $sigHeader): void
    {
        $event = Webhook::constructEvent(
            $payload,
            $sigHeader,
            config('services.stripe.webhook_secret')
        );

        match ($event->type) {
            'checkout.session.completed' => $this->handleCheckoutCompleted($event->data->object),
            'invoice.paid' => $this->handleInvoicePaid($event->data->object),
            'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
            'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
            default => null,
        };
    }

    protected function handleCheckoutCompleted(object $session): void
    {
        $userId = $session->metadata->user_id ?? null;
        if (!$userId) return;

        $user = User::find($userId);
        if (!$user) return;

        $type = $session->metadata->type ?? 'subscription';

        if ($type === 'subscription') {
            // Retrieve the Stripe subscription with price details
            $stripeSub = null;
            if ($session->subscription) {
                try {
                    $stripeSub = Subscription::retrieve([
                        'id' => $session->subscription,
                        'expand' => ['items.data.price'],
                    ]);
                } catch (\Exception $e) {
                    Log::warning("Failed to retrieve Stripe subscription details", ['error' => $e->getMessage()]);
                }
            }

            $this->activatePremium($user, 'stripe', $session->subscription, $stripeSub);

            Purchase::create([
                'user_id' => $user->id,
                'platform' => 'stripe',
                'product_id' => 'premium_subscription',
                'type' => 'subscription',
                'amount_cents' => $session->amount_total ?? 0,
                'currency' => $session->currency ?? 'usd',
                'transaction_id' => $session->subscription,
                'status' => 'completed',
            ]);

            try {
                broadcast(new PremiumStatusChanged(
                    userId: $user->id,
                    isPremium: true,
                    platform: 'stripe',
                    expiresAt: $user->premium_expires_at?->toIso8601String(),
                    purchaseType: 'subscription',
                    status: 'activated',
                ));
            } catch (\Exception $e) {
                Log::warning('Failed to broadcast PremiumStatusChanged', ['error' => $e->getMessage()]);
            }
        } else {
            $unlockableId = $session->metadata->unlockable_id ?? null;
            $unlockable = $unlockableId ? Unlockable::find($unlockableId) : null;

            $purchase = Purchase::create([
                'user_id' => $user->id,
                'platform' => 'stripe',
                'product_id' => $unlockable?->stripe_price_id ?? 'unknown',
                'type' => 'one_time',
                'amount_cents' => $session->amount_total ?? 0,
                'currency' => $session->currency ?? 'usd',
                'transaction_id' => $session->payment_intent,
                'status' => 'completed',
                'unlockable_id' => $unlockableId,
            ]);

            if ($unlockable) {
                $this->grantUnlockable($user, $unlockable);
            }

            $activeSub = $user->activeSubscription();
            try {
                broadcast(new PremiumStatusChanged(
                    userId: $user->id,
                    isPremium: $user->isPremium(),
                    platform: $activeSub?->platform,
                    expiresAt: $user->premium_expires_at?->toIso8601String(),
                    purchaseType: 'one_time',
                    status: 'purchase_completed',
                ));
            } catch (\Exception $e) {
                Log::warning('Failed to broadcast PremiumStatusChanged', ['error' => $e->getMessage()]);
            }
        }
    }

    protected function handleInvoicePaid(object $invoice): void
    {
        $customerId = $invoice->customer;
        $user = $this->findUserByStripeCustomer($customerId);
        if (!$user) return;

        $activeSub = $user->activeSubscription();

        // Renewal - extend premium
        if ($user->is_premium && $activeSub && $activeSub->platform === 'stripe') {
            $subscriptionId = $invoice->subscription;
            if ($subscriptionId) {
                $sub = Subscription::retrieve($subscriptionId);
                $periodEnd = \Carbon\Carbon::createFromTimestamp($sub->current_period_end);

                $activeSub->update([
                    'current_period_end' => $periodEnd,
                    'cancel_at_period_end' => $sub->cancel_at_period_end,
                ]);

                $user->premium_expires_at = $periodEnd;
                $user->save();
            }
        }
    }

    protected function handleSubscriptionDeleted(object $subscription): void
    {
        $customerId = $subscription->customer;
        $user = $this->findUserByStripeCustomer($customerId);
        if (!$user) return;

        $this->deactivatePremium($user);

        try {
            broadcast(new PremiumStatusChanged(
                userId: $user->id,
                isPremium: false,
                platform: null,
                expiresAt: null,
                purchaseType: 'subscription',
                status: 'cancelled',
            ));
        } catch (\Exception $e) {
            Log::warning('Failed to broadcast PremiumStatusChanged', ['error' => $e->getMessage()]);
        }
    }

    protected function handleSubscriptionUpdated(object $subscription): void
    {
        $customerId = $subscription->customer;
        $user = $this->findUserByStripeCustomer($customerId);
        if (!$user) return;

        $userSub = UserSubscription::where('user_id', $user->id)
            ->where('platform', 'stripe')
            ->where('status', 'active')
            ->first();

        if (!$userSub) return;

        if (!$subscription->current_period_end) return;

        $periodEnd = \Carbon\Carbon::createFromTimestamp($subscription->current_period_end);

        if ($subscription->status === 'active') {
            $userSub->update([
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => $subscription->cancel_at_period_end ?? false,
                'status' => 'active',
            ]);

            $user->premium_expires_at = $periodEnd;
            $user->save();
        } elseif (in_array($subscription->status, ['canceled', 'unpaid', 'past_due'])) {
            $newStatus = $subscription->status === 'past_due' ? 'past_due' : 'canceled';
            $userSub->update([
                'status' => $newStatus,
                'cancel_at_period_end' => $subscription->cancel_at_period_end ?? false,
                'current_period_end' => $periodEnd,
            ]);

            // If canceled, stays active until period end
            if ($subscription->status === 'canceled') {
                $user->premium_expires_at = $periodEnd;
                $user->save();
            }
        }
    }

    // ── Apple IAP ───────────────────────────────────────────────

    public function verifyAppleReceipt(string $receiptData): ?array
    {
        if (config('app.env') !== 'production' && env('PAYMENT_SANDBOX_MODE', false)) {
            return ['status' => 0, 'sandbox' => true];
        }

        // Try production first
        $response = Http::post('https://buy.itunes.apple.com/verifyReceipt', [
            'receipt-data' => $receiptData,
            'password' => config('services.apple.shared_secret'),
            'exclude-old-transactions' => true,
        ]);

        $result = $response->json();

        // If status 21007, retry against sandbox
        if (($result['status'] ?? -1) === 21007) {
            $response = Http::post('https://sandbox.itunes.apple.com/verifyReceipt', [
                'receipt-data' => $receiptData,
                'password' => config('services.apple.shared_secret'),
                'exclude-old-transactions' => true,
            ]);
            $result = $response->json();
        }

        return ($result['status'] ?? -1) === 0 ? $result : null;
    }

    public function processApplePurchase(User $user, string $receiptData, string $productId, string $transactionId): Purchase
    {
        $verified = $this->verifyAppleReceipt($receiptData);

        if (!$verified) {
            return Purchase::create([
                'user_id' => $user->id,
                'platform' => 'apple',
                'product_id' => $productId,
                'type' => $this->inferPurchaseType($productId),
                'transaction_id' => $transactionId,
                'receipt_data' => $receiptData,
                'status' => 'failed',
            ]);
        }

        $type = $this->inferPurchaseType($productId);
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'platform' => 'apple',
            'product_id' => $productId,
            'type' => $type,
            'transaction_id' => $transactionId,
            'receipt_data' => $receiptData,
            'status' => 'completed',
        ]);

        $this->grantPurchase($user, $purchase);

        return $purchase;
    }

    // ── Google Play IAP ─────────────────────────────────────────

    public function verifyGooglePurchase(string $productId, string $purchaseToken): ?array
    {
        if (config('app.env') !== 'production' && env('PAYMENT_SANDBOX_MODE', false)) {
            return ['purchaseState' => 0, 'sandbox' => true];
        }

        // Google Play verification requires service account credentials
        // This is a simplified version - full implementation would use Google API client
        Log::info('Google Play verification requested', ['product_id' => $productId]);

        return ['purchaseState' => 0];
    }

    public function processGooglePurchase(User $user, string $productId, string $purchaseToken, string $transactionId): Purchase
    {
        $verified = $this->verifyGooglePurchase($productId, $purchaseToken);

        if (!$verified || ($verified['purchaseState'] ?? -1) !== 0) {
            return Purchase::create([
                'user_id' => $user->id,
                'platform' => 'google',
                'product_id' => $productId,
                'type' => $this->inferPurchaseType($productId),
                'transaction_id' => $transactionId,
                'receipt_data' => $purchaseToken,
                'status' => 'failed',
            ]);
        }

        $type = $this->inferPurchaseType($productId);
        $purchase = Purchase::create([
            'user_id' => $user->id,
            'platform' => 'google',
            'product_id' => $productId,
            'type' => $type,
            'transaction_id' => $transactionId,
            'receipt_data' => $purchaseToken,
            'status' => 'completed',
        ]);

        $this->grantPurchase($user, $purchase);

        return $purchase;
    }

    // ── Grant logic ─────────────────────────────────────────────

    public function grantPurchase(User $user, Purchase $purchase): void
    {
        if ($purchase->type === 'subscription') {
            $this->activatePremium($user, $purchase->platform, $purchase->transaction_id);
            return;
        }

        // One-time: find matching unlockable by product ID
        $unlockable = $purchase->unlockable;
        if (!$unlockable) {
            $unlockable = Unlockable::where('apple_product_id', $purchase->product_id)
                ->orWhere('google_product_id', $purchase->product_id)
                ->first();

            if ($unlockable) {
                $purchase->unlockable_id = $unlockable->id;
                $purchase->save();
            }
        }

        if ($unlockable) {
            $this->grantUnlockable($user, $unlockable);
        }
    }

    public function grantUnlockable(User $user, Unlockable $unlockable): void
    {
        UserUnlockable::firstOrCreate(
            ['user_id' => $user->id, 'unlockable_id' => $unlockable->id],
            ['unlocked_at' => now()]
        );
    }

    public function activatePremium(User $user, string $platform, ?string $subscriptionId = null, ?object $stripeSubscription = null): void
    {
        // Set a generous expiry so the check works even if webhook is delayed
        $expiresAt = now()->addDays(35);

        // Extract plan details from Stripe subscription if available
        $planInterval = null;
        $planIntervalCount = 1;
        $planAmountCents = null;
        $planCurrency = 'usd';
        $periodEnd = $expiresAt;
        $cancelAtPeriodEnd = false;

        if ($stripeSubscription) {
            if ($stripeSubscription->current_period_end) {
                $periodEnd = \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
                $expiresAt = $periodEnd;
            }
            $cancelAtPeriodEnd = $stripeSubscription->cancel_at_period_end ?? false;

            $item = $stripeSubscription->items->data[0] ?? null;
            if ($item?->price) {
                $planInterval = $item->price->recurring->interval ?? null;
                $planIntervalCount = $item->price->recurring->interval_count ?? 1;
                $planAmountCents = $item->price->unit_amount;
                $planCurrency = $item->price->currency ?? 'usd';
            }
        }

        // Create or update UserSubscription
        $userSub = UserSubscription::where('user_id', $user->id)
            ->where('platform', $platform)
            ->whereIn('status', ['active', 'canceled'])
            ->first();

        if ($userSub) {
            $userSub->update([
                'subscription_id' => $subscriptionId ?? $userSub->subscription_id,
                'status' => 'active',
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'plan_interval' => $planInterval ?? $userSub->plan_interval,
                'plan_interval_count' => $planIntervalCount,
                'plan_amount_cents' => $planAmountCents ?? $userSub->plan_amount_cents,
                'plan_currency' => $planCurrency,
            ]);
        } else {
            UserSubscription::create([
                'user_id' => $user->id,
                'platform' => $platform,
                'subscription_id' => $subscriptionId,
                'status' => 'active',
                'current_period_end' => $periodEnd,
                'cancel_at_period_end' => $cancelAtPeriodEnd,
                'plan_interval' => $planInterval,
                'plan_interval_count' => $planIntervalCount,
                'plan_amount_cents' => $planAmountCents,
                'plan_currency' => $planCurrency,
            ]);
        }

        // Update denormalized fields on user
        $user->is_premium = true;
        $user->premium_expires_at = $expiresAt;
        $user->save();
    }

    public function deactivatePremium(User $user): void
    {
        // Mark all active subscriptions as expired
        UserSubscription::where('user_id', $user->id)
            ->whereIn('status', ['active', 'canceled', 'past_due'])
            ->update(['status' => 'expired']);

        $user->is_premium = false;
        $user->premium_expires_at = null;
        $user->save();
    }

    // ── Helpers ──────────────────────────────────────────────────

    protected function findUserByStripeCustomer(string $customerId): ?User
    {
        $paymentCustomer = PaymentCustomer::where('platform', 'stripe')
            ->where('platform_customer_id', $customerId)
            ->first();

        return $paymentCustomer ? User::find($paymentCustomer->user_id) : null;
    }

    protected function inferPurchaseType(string $productId): string
    {
        // Convention: subscription product IDs contain 'premium' or 'subscription'
        if (str_contains(strtolower($productId), 'premium') || str_contains(strtolower($productId), 'subscription')) {
            return 'subscription';
        }

        return 'one_time';
    }
}
