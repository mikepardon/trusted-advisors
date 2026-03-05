<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameRule;
use App\Models\Purchase;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminPaymentController extends Controller
{
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
        return response()->json([
            'premium_price_id' => config('services.stripe.premium_price_id'),
            'app_review_enabled' => GameRule::getValue('app_review_enabled', false),
            'app_review_trigger' => GameRule::getValue('app_review_trigger', ['type' => 'games_completed', 'value' => 3]),
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'app_review_enabled' => 'sometimes|boolean',
            'app_review_trigger' => 'sometimes|array',
            'app_review_trigger.type' => 'sometimes|string|in:games_completed,level',
            'app_review_trigger.value' => 'sometimes|integer|min:1',
        ]);

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

        return response()->json(['message' => 'Settings updated.']);
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

        return response()->json(['message' => "Premium gifted to {$user->name} for {$label}."]);
    }

    public function revokePremium(User $user): JsonResponse
    {
        $paymentService = app(PaymentService::class);
        $paymentService->deactivatePremium($user);

        return response()->json(['message' => "Premium revoked from {$user->name}."]);
    }
}
