<?php

namespace App\Http\Controllers;

use App\Models\GameRule;
use App\Models\PaymentCustomer;
use App\Models\Purchase;
use App\Models\Unlockable;
use App\Models\UserSubscription;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Subscription;

class PaymentController extends Controller
{
    public function __construct(protected PaymentService $paymentService) {}

    public function premiumPrice(): JsonResponse
    {
        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $price = \Stripe\Price::retrieve(config('services.stripe.premium_price_id'));

            return response()->json([
                'amount_cents' => $price->unit_amount,
                'currency' => strtoupper($price->currency),
                'interval' => $price->recurring->interval ?? null,
                'interval_count' => $price->recurring->interval_count ?? 1,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Could not fetch price.'], 500);
        }
    }

    public function premiumStatus(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeSub = $user->activeSubscription();

        return response()->json([
            'is_premium' => $user->isPremium(),
            'premium_platform' => $activeSub?->platform,
            'premium_expires_at' => $user->premium_expires_at,
        ]);
    }

    public function stripeCheckout(Request $request): JsonResponse
    {
        $request->validate([
            'mode' => 'required|in:subscription,one_time',
            'unlockable_id' => 'required_if:mode,one_time|integer|exists:unlockables,id',
        ]);

        $user = $request->user();
        $mode = $request->mode;
        $unlockable = null;

        if ($mode === 'one_time') {
            $unlockable = Unlockable::findOrFail($request->unlockable_id);
            if (!$unlockable->stripe_price_id) {
                return response()->json(['error' => 'This item is not available for Stripe purchase.'], 422);
            }
        }

        $session = $this->paymentService->createStripeCheckoutSession($user, $mode, $unlockable);

        return response()->json(['url' => $session->url]);
    }

    public function iapVerify(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'required|in:apple,google',
            'product_id' => 'required|string',
            'transaction_id' => 'required|string',
            'receipt_data' => 'required|string',
        ]);

        $user = $request->user();

        // Check for duplicate transaction
        $existing = Purchase::where('transaction_id', $request->transaction_id)
            ->where('status', 'completed')
            ->first();

        if ($existing) {
            return response()->json(['message' => 'Purchase already processed.', 'purchase' => $existing]);
        }

        if ($request->platform === 'apple') {
            $purchase = $this->paymentService->processApplePurchase(
                $user,
                $request->receipt_data,
                $request->product_id,
                $request->transaction_id
            );
        } else {
            $purchase = $this->paymentService->processGooglePurchase(
                $user,
                $request->product_id,
                $request->receipt_data,
                $request->transaction_id
            );
        }

        if ($purchase->status === 'failed') {
            return response()->json(['error' => 'Purchase verification failed.'], 422);
        }

        return response()->json([
            'message' => 'Purchase verified and granted.',
            'purchase' => $purchase,
            'is_premium' => $user->fresh()->isPremium(),
        ]);
    }

    public function restore(Request $request): JsonResponse
    {
        $request->validate([
            'platform' => 'required|in:apple,google',
            'receipts' => 'required|array',
            'receipts.*.product_id' => 'required|string',
            'receipts.*.transaction_id' => 'required|string',
            'receipts.*.receipt_data' => 'required|string',
        ]);

        $user = $request->user();
        $restored = [];

        foreach ($request->receipts as $receipt) {
            $existing = Purchase::where('transaction_id', $receipt['transaction_id'])
                ->where('status', 'completed')
                ->first();

            if ($existing) {
                // Re-grant in case user lost data
                $this->paymentService->grantPurchase($user, $existing);
                $restored[] = $receipt['product_id'];
                continue;
            }

            if ($request->platform === 'apple') {
                $purchase = $this->paymentService->processApplePurchase(
                    $user,
                    $receipt['receipt_data'],
                    $receipt['product_id'],
                    $receipt['transaction_id']
                );
            } else {
                $purchase = $this->paymentService->processGooglePurchase(
                    $user,
                    $receipt['product_id'],
                    $receipt['receipt_data'],
                    $receipt['transaction_id']
                );
            }

            if ($purchase->status === 'completed') {
                $restored[] = $receipt['product_id'];
            }
        }

        return response()->json([
            'message' => count($restored) . ' purchase(s) restored.',
            'restored' => $restored,
            'is_premium' => $user->fresh()->isPremium(),
        ]);
    }

    public function managePremium(Request $request): JsonResponse
    {
        $user = $request->user();

        $paymentCustomer = PaymentCustomer::where('user_id', $user->id)
            ->where('platform', 'stripe')
            ->first();

        if (!$paymentCustomer) {
            return response()->json(['error' => 'No Stripe subscription found.'], 404);
        }

        $session = $this->paymentService->createPortalSession($user);

        return response()->json(['url' => $session->url]);
    }

    public function shouldPromptReview(Request $request): JsonResponse
    {
        $user = $request->user();

        // Already prompted
        if ($user->app_review_prompted_at) {
            return response()->json(['should_prompt' => false]);
        }

        // Check if review prompting is enabled
        $enabled = GameRule::getValue('app_review_enabled', false);
        if (!$enabled) {
            return response()->json(['should_prompt' => false]);
        }

        // Check trigger condition
        $trigger = GameRule::getValue('app_review_trigger', ['type' => 'games_completed', 'value' => 3]);
        $triggerType = $trigger['type'] ?? 'games_completed';
        $triggerValue = $trigger['value'] ?? 3;

        $meetsCondition = match ($triggerType) {
            'games_completed' => $user->games()
                ->whereIn('status', ['completed', 'won', 'lost'])
                ->count() >= $triggerValue,
            'level' => ($user->level ?? 1) >= $triggerValue,
            default => false,
        };

        return response()->json(['should_prompt' => $meetsCondition]);
    }

    public function markReviewPrompted(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->app_review_prompted_at = now();
        $user->save();

        return response()->json(['message' => 'Review prompt recorded.']);
    }

    public function subscriptionDetails(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeSub = $user->activeSubscription();

        $details = [
            'is_premium' => $user->isPremium(),
            'platform' => $activeSub?->platform,
            'premium_expires_at' => $user->premium_expires_at,
            'cancel_at_period_end' => $activeSub?->cancel_at_period_end ?? false,
            'status' => $user->isPremium() ? 'active' : 'inactive',
        ];

        if ($activeSub) {
            $details['current_period_end'] = $activeSub->current_period_end?->toIso8601String();
            $details['interval'] = $activeSub->plan_interval;
            $details['interval_count'] = $activeSub->plan_interval_count;
            $details['amount_cents'] = $activeSub->plan_amount_cents;
            $details['currency'] = strtoupper($activeSub->plan_currency);
        }

        // Backfill from Stripe if plan data is missing
        if ($activeSub && $activeSub->platform === 'stripe' && $activeSub->subscription_id && !$activeSub->plan_interval) {
            try {
                \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
                $sub = Subscription::retrieve(['id' => $activeSub->subscription_id, 'expand' => ['items.data.price']]);

                $details['status'] = $sub->status;
                $details['current_period_end'] = \Carbon\Carbon::createFromTimestamp($sub->current_period_end)->toIso8601String();
                $details['cancel_at_period_end'] = $sub->cancel_at_period_end;

                $item = $sub->items->data[0] ?? null;
                if ($item?->price) {
                    $details['interval'] = $item->price->recurring->interval ?? null;
                    $details['interval_count'] = $item->price->recurring->interval_count ?? 1;
                    $details['amount_cents'] = $item->price->unit_amount;
                    $details['currency'] = strtoupper($item->price->currency);
                }

                // Backfill the local record
                $activeSub->update([
                    'plan_interval' => $details['interval'],
                    'plan_interval_count' => $details['interval_count'],
                    'plan_amount_cents' => $details['amount_cents'],
                    'plan_currency' => strtolower($details['currency']),
                    'current_period_end' => \Carbon\Carbon::createFromTimestamp($sub->current_period_end),
                    'cancel_at_period_end' => $sub->cancel_at_period_end,
                ]);
            } catch (\Exception $e) {
                // Stripe sub may no longer exist; return stored data
            }
        }

        return response()->json($details);
    }

    public function cancelSubscription(Request $request): JsonResponse
    {
        $user = $request->user();
        $activeSub = $user->activeSubscription();

        if (!$activeSub || $activeSub->platform !== 'stripe' || !$activeSub->subscription_id) {
            return response()->json(['error' => 'No active Stripe subscription found.'], 404);
        }

        try {
            \Stripe\Stripe::setApiKey(config('services.stripe.secret'));
            $sub = Subscription::update($activeSub->subscription_id, [
                'cancel_at_period_end' => true,
            ]);

            $endsAt = $sub->current_period_end
                ? \Carbon\Carbon::createFromTimestamp($sub->current_period_end)
                : $user->premium_expires_at;

            // Persist cancel_at_period_end locally
            $activeSub->update([
                'cancel_at_period_end' => true,
                'current_period_end' => $endsAt,
            ]);

            if ($endsAt) {
                $user->premium_expires_at = $endsAt;
                $user->save();
            }

            return response()->json([
                'message' => 'Subscription will cancel at end of billing period.',
                'ends_at' => $endsAt?->toIso8601String(),
                'cancel_at_period_end' => true,
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to cancel subscription.'], 500);
        }
    }
}
