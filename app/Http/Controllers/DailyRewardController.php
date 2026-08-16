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
     * Cycling 7-day reward ladder: coins on days 1–3, XP on days 4–6, a coin payoff on
     * day 7. Streak is driven by daily claim cadence, not re-authentication.
     *
     * @var list<array{type: string, amount: int}>
     */
    private const LADDER = [
        ['type' => 'coins', 'amount' => 10],
        ['type' => 'coins', 'amount' => 15],
        ['type' => 'coins', 'amount' => 20],
        ['type' => 'xp', 'amount' => 30],
        ['type' => 'xp', 'amount' => 40],
        ['type' => 'xp', 'amount' => 50],
        ['type' => 'coins', 'amount' => 100],
    ];

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

        // Lock the user row and re-check the claim inside the transaction: without this,
        // two concurrent requests both pass an outside-the-lock "already claimed today"
        // check and each grant the reward, farming it.
        $result = DB::transaction(function () use ($userId): array {
            $user = User::query()->whereKey($userId)->lockForUpdate()->first();

            if ($user->daily_reward_claimed_at?->isToday()) {
                return ['claimed' => true];
            }

            $streak = $this->streakForToday($user);
            $day = (($streak - 1) % 7) + 1;
            $reward = self::LADDER[$day - 1];

            if ($reward['type'] === 'xp') {
                $user->xp += $reward['amount'];
                $user->level = User::calculateLevel($user->xp);
            } else {
                $user->coins += $reward['amount'];
            }

            $user->daily_reward_streak = $streak;
            $user->daily_reward_claimed_at = now();
            $user->save();

            if ($reward['type'] === 'coins') {
                $user->recordCoinTransaction($reward['amount'], 'earn', 'daily_reward', null, "Daily reward — day {$day}");
            }

            return [
                'claimed' => false,
                'type' => $reward['type'],
                'amount' => $reward['amount'],
                'streak' => $streak,
                'day' => $day,
                'new_coins' => $user->coins,
                'new_xp' => $user->xp,
                'new_level' => $user->level,
            ];
        });

        if ($result['claimed'] === true) {
            return response()->json(['message' => 'Already claimed today.'], 422);
        }

        return response()->json([
            'type' => $result['type'],
            'amount' => $result['amount'],
            'streak' => $result['streak'],
            'day' => $result['day'],
            'new_coins' => $result['new_coins'],
            'new_xp' => $result['new_xp'],
            'new_level' => $result['new_level'],
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
