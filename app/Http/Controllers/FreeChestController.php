<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class FreeChestController extends Controller
{
    /**
     * Hours between free-chest claims. Twice-a-day cadence: frequent enough to
     * pull players back, spaced enough that it stays a treat rather than a grind.
     */
    private const COOLDOWN_HOURS = 12;

    private const REWARD_MIN = 20;

    private const REWARD_MAX = 60;

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $nextAt = $user->free_chest_claimed_at?->addHours(self::COOLDOWN_HOURS);
        $available = $nextAt === null || $nextAt->isPast();

        return response()->json([
            'available' => $available,
            'next_available_at' => $available ? null : $nextAt->toIso8601ZuluString('millisecond'),
            'reward_min' => self::REWARD_MIN,
            'reward_max' => self::REWARD_MAX,
        ]);
    }

    public function claim(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $coins = random_int(self::REWARD_MIN, self::REWARD_MAX);

        // Lock the user row and re-check the cooldown inside the transaction. The
        // previous check ran before the lock, so two concurrent requests could both
        // pass it and each claim a chest within the cooldown window.
        $result = DB::transaction(function () use ($userId, $coins): array {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();
            $nextAt = $user->free_chest_claimed_at?->addHours(self::COOLDOWN_HOURS);

            if ($nextAt !== null && $nextAt->isFuture()) {
                return ['cooling' => true, 'next_available_at' => $nextAt];
            }

            $user->coins += $coins;
            $user->free_chest_claimed_at = now();
            $user->save();
            $user->recordCoinTransaction($coins, 'earn', 'free_chest', null, 'Free chest');

            return ['cooling' => false, 'new_coins' => $user->coins];
        });

        if ($result['cooling'] === true) {
            return response()->json([
                'message' => 'The chest is still refilling.',
                'next_available_at' => $result['next_available_at']->toIso8601ZuluString('millisecond'),
            ], 422);
        }

        return response()->json([
            'coins' => $coins,
            'new_coins' => $result['new_coins'],
            'next_available_at' => now()->addHours(self::COOLDOWN_HOURS)->toIso8601ZuluString('millisecond'),
        ]);
    }
}
