<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DailyRewardController extends Controller
{
    /**
     * Back-weighted 7-day coin ladder: modest early days that ramp to the Day 7
     * payoff, so the reward is mostly in sustaining the streak. The cycle then
     * repeats. Streak is driven by daily claim cadence, not re-authentication.
     *
     * @var list<int>
     */
    private const LADDER = [10, 10, 15, 20, 30, 40, 50];

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        $claimedToday = $user->daily_reward_claimed_at?->isToday() ?? false;
        $streak = $this->streakForToday($user);
        $day = (($streak - 1) % 7) + 1;

        return response()->json([
            'available' => ! $claimedToday,
            'streak' => $streak,
            'day' => $day,
            'ladder' => self::LADDER,
            'reward' => self::LADDER[$day - 1],
        ]);
    }

    public function claim(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Lock the user row and re-check the claim inside the transaction:
        // without this, two concurrent requests both pass an outside-the-lock
        // "already claimed today" check and each grant coins, farming the reward.
        $result = DB::transaction(function () use ($userId): array {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();

            if ($user->daily_reward_claimed_at?->isToday()) {
                return ['claimed' => true];
            }

            $streak = $this->streakForToday($user);
            $day = (($streak - 1) % 7) + 1;
            $coins = self::LADDER[$day - 1];

            $user->coins += $coins;
            $user->daily_reward_streak = $streak;
            $user->daily_reward_claimed_at = now();
            $user->save();
            $user->recordCoinTransaction($coins, 'earn', 'daily_reward', null, "Daily reward — day {$day}");

            return [
                'claimed' => false,
                'coins' => $coins,
                'streak' => $streak,
                'day' => $day,
                'new_coins' => $user->coins,
            ];
        });

        if ($result['claimed'] === true) {
            return response()->json(['message' => 'Already claimed today.'], 422);
        }

        return response()->json([
            'coins' => $result['coins'],
            'streak' => $result['streak'],
            'day' => $result['day'],
            'new_coins' => $result['new_coins'],
        ]);
    }

    /**
     * The streak that applies to today's reward: continue if the last claim was
     * yesterday (or already today), otherwise the streak has lapsed and resets.
     */
    private function streakForToday(User $user): int
    {
        $last = $user->daily_reward_claimed_at;

        if ($last === null) {
            return 1;
        }

        if ($last->isToday()) {
            return $user->daily_reward_streak;
        }

        if ($last->isYesterday()) {
            return $user->daily_reward_streak + 1;
        }

        return 1;
    }
}
