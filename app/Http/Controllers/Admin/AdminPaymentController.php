<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRule;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PaymentService;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
    use AuditsAdminActions;

    public function subscribers(): JsonResponse
    {
        $subscribers = User::where('is_premium', true)
            ->with(['subscriptions' => function ($q) {
                $q->where('status', 'active')->latest()->limit(1);
            }])
            ->select('id', 'name', 'email', 'premium_expires_at', 'created_at')
            ->orderByDesc('premium_expires_at')
            ->get()
            ->map(function ($user) {
                $sub = $user->subscriptions->first();
                $user->platform = $sub?->platform ?? 'unknown';
                unset($user->subscriptions);

                return $user;
            });

        return response()->json(['subscribers' => $subscribers]);
    }

    public function purchases(Request $request): JsonResponse
    {
        $purchases = Purchase::with('user:id,name,email')
            ->orderByDesc('created_at')
            ->limit($request->get('limit', 50))
            ->get();

        return response()->json(['purchases' => $purchases]);
    }

    public function settings(): JsonResponse
    {
        // config() has already been overridden with any admin-set Stripe values at boot,
        // so these are the effective (resolved) values. Secrets are only ever returned masked.
        return response()->json([
            'payments_enabled' => GameRule::getValue('payments_enabled', true),
            'premium_price_id' => config('services.stripe.premium_price_id'),
            'app_review_enabled' => GameRule::getValue('app_review_enabled', false),
            'app_review_trigger' => GameRule::getValue('app_review_trigger', ['type' => 'games_completed', 'value' => 3]),
            'stripe' => $this->stripeStatus(),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'payments_enabled' => 'sometimes|boolean',
            'app_review_enabled' => 'sometimes|boolean',
            'app_review_trigger' => 'sometimes|array',
            'app_review_trigger.type' => 'sometimes|string|in:games_completed,level',
            'app_review_trigger.value' => 'sometimes|integer|min:1',
            'stripe_key' => 'sometimes|nullable|string|max:255',
            'stripe_secret' => 'sometimes|nullable|string|max:255',
            'stripe_webhook_secret' => 'sometimes|nullable|string|max:255',
            'stripe_premium_price_id' => 'sometimes|nullable|string|max:255',
        ]);

        if ($request->has('payments_enabled')) {
            GameRule::updateOrCreate(
                ['key' => 'payments_enabled'],
                ['value' => $request->payments_enabled]
            );
        }

        if ($request->has('app_review_enabled')) {
            GameRule::updateOrCreate(
                ['key' => 'app_review_enabled'],
                ['value' => $request->app_review_enabled]
            );
        }

        if ($request->has('app_review_trigger')) {
            GameRule::updateOrCreate(
                ['key' => 'app_review_trigger'],
                ['value' => $request->app_review_trigger]
            );
        }

        // Stripe config: a blank value clears the override (reverts to .env); secrets stored
        // encrypted at rest.
        $this->storeStripeSetting($request, 'stripe_key', 'stripe_key', false);
        $this->storeStripeSetting($request, 'stripe_premium_price_id', 'stripe_premium_price_id', false);
        $this->storeStripeSetting($request, 'stripe_secret', 'stripe_secret_enc', true);
        $this->storeStripeSetting($request, 'stripe_webhook_secret', 'stripe_webhook_secret_enc', true);

        return response()->json(['message' => 'Settings updated.']);
    }

    /**
     * The active recurring Stripe prices, so an admin can pick which plan is "premium".
     */
    public function stripePrices(): JsonResponse
    {
        $secret = $this->resolvedStripe('stripe_secret_enc', 'secret', true);
        if (blank($secret)) {
            return response()->json(['error' => 'Add your Stripe secret key and save before loading plans.'], 422);
        }

        try {
            $stripe = new \Stripe\StripeClient($secret);
            $prices = $stripe->prices->all([
                'active' => true,
                'type' => 'recurring',
                'limit' => 100,
                'expand' => ['data.product'],
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['error' => 'Could not load plans from Stripe: '.$exception->getMessage()], 502);
        }

        $plans = collect($prices->data)->map(function ($price): array {
            $product = is_object($price->product) ? ($price->product->name ?? 'Product') : (string) $price->product;
            $currency = strtoupper((string) $price->currency);
            $count = (int) ($price->recurring->interval_count ?? 1);
            $interval = (string) ($price->recurring->interval ?? '');
            $every = $count > 1 ? "{$count} {$interval}s" : $interval;

            // Minor units → major, via brick/math (no float) — assumes 2-decimal currencies.
            $amount = \Brick\Math\BigDecimal::of((int) $price->unit_amount)
                ->dividedBy(100, 2, \Brick\Math\RoundingMode::HALF_UP);

            $label = trim("{$product} — {$amount} {$currency} / {$every}");
            if (filled($price->nickname)) {
                $label = "{$product} ({$price->nickname}) — {$amount} {$currency} / {$every}";
            }

            return [
                'id' => $price->id,
                'label' => $label,
            ];
        })->values();

        return response()->json(['prices' => $plans]);
    }

    /**
     * Store a Stripe setting override in GameRule, or delete it when blank (revert to env).
     */
    private function storeStripeSetting(Request $request, string $field, string $ruleKey, bool $encrypt): void
    {
        if (! $request->has($field)) {
            return;
        }

        $value = $request->input($field);

        if (blank($value)) {
            GameRule::where('key', $ruleKey)->delete();

            return;
        }

        GameRule::updateOrCreate(
            ['key' => $ruleKey],
            ['value' => $encrypt ? encrypt(trim($value)) : trim($value)],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function stripeStatus(): array
    {
        $secret = $this->resolvedStripe('stripe_secret_enc', 'secret', true);
        $webhook = $this->resolvedStripe('stripe_webhook_secret_enc', 'webhook_secret', true);

        return [
            // Publishable key (pk_...) is public and used client-side — safe to return in full.
            'key' => $this->resolvedStripe('stripe_key', 'key', false),
            'secret_set' => filled($secret),
            'secret_masked' => $this->mask($secret),
            'webhook_secret_set' => filled($webhook),
            'webhook_secret_masked' => $this->mask($webhook),
            'premium_price_id' => $this->resolvedStripe('stripe_premium_price_id', 'premium_price_id', false),
        ];
    }

    /**
     * Resolve a Stripe value admin-first (GameRule, decrypting when needed), then .env.
     */
    private function resolvedStripe(string $ruleKey, string $configKey, bool $encrypted): ?string
    {
        $stored = GameRule::getValue($ruleKey);
        if (filled($stored)) {
            if (! $encrypted) {
                return $stored;
            }
            try {
                return decrypt($stored);
            } catch (\Throwable) {
                // Undecryptable — fall through to env.
            }
        }

        return config("services.stripe.{$configKey}");
    }

    private function mask(?string $secret): string
    {
        if (blank($secret)) {
            return '';
        }

        $visible = mb_substr($secret, -4);

        return str_repeat('•', min(mb_strlen($secret) - 4, 8)).$visible;
    }

    public function grantPremium(Request $request, User $user): JsonResponse
    {
        $request->validate([
            'duration' => 'sometimes|string|in:1_month,3_months,6_months,1_year,lifetime',
        ]);

        $duration = $request->input('duration', '1_month');
        $paymentService = app(PaymentService::class);
        $paymentService->activatePremium($user, 'gift');

        // Set expiry based on duration
        $expiresAt = match ($duration) {
            '1_month' => now()->addMonth(),
            '3_months' => now()->addMonths(3),
            '6_months' => now()->addMonths(6),
            '1_year' => now()->addYear(),
            'lifetime' => now()->addYears(100),
        };

        $user->premium_expires_at = $expiresAt;
        $user->save();

        $sub = $user->activeSubscription();
        if ($sub) {
            $sub->update([
                'current_period_end' => $expiresAt,
                'plan_interval' => $duration === 'lifetime' ? null : match ($duration) {
                    '1_month' => 'month',
                    '3_months' => 'month',
                    '6_months' => 'month',
                    '1_year' => 'year',
                },
                'plan_interval_count' => match ($duration) {
                    '1_month' => 1,
                    '3_months' => 3,
                    '6_months' => 6,
                    '1_year' => 1,
                    'lifetime' => 1,
                },
            ]);
        }

        $label = str_replace('_', ' ', $duration);
        $this->auditLog('grant_premium', 'User', $user->id, null, ['duration' => $duration]);

        return response()->json(['message' => "Premium gifted to {$user->name} for {$label}."]);
    }

    public function revokePremium(User $user): JsonResponse
    {
        $paymentService = app(PaymentService::class);
        $paymentService->deactivatePremium($user);
        $this->auditLog('revoke_premium', 'User', $user->id);

        return response()->json(['message' => "Premium revoked from {$user->name}."]);
    }
}
