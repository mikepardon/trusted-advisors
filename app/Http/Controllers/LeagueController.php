<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\LeagueResult;
use App\Services\LeagueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeagueController extends Controller
{
    public function __construct(private readonly LeagueService $league) {}

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'standings' => $this->league->standings($request->user()),
            'tiers' => LeagueService::TIERS,
        ]);
    }

    /**
     * The most recent finalised-week result the player has not yet been shown, resolved to
     * tier names/colours for the end-of-week overview. Null when there is nothing to show.
     */
    public function lastResult(Request $request): JsonResponse
    {
        $result = LeagueResult::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('seen_at')
            ->orderByDesc('week_start')
            ->orderByDesc('id')
            ->first();

        if ($result === null) {
            return response()->json(['result' => null]);
        }

        $tierCount = count(LeagueService::TIERS);
        $before = max(0, min($result->tier_before, $tierCount - 1));
        $after = max(0, min($result->tier_after, $tierCount - 1));

        return response()->json([
            'result' => [
                'id' => $result->id,
                'week_start' => $result->week_start?->toDateString(),
                'rank' => $result->rank,
                'total' => $result->total,
                'coins_earned' => $result->coins_earned,
                'promoted' => $after > $before,
                'demoted' => $after < $before,
                'tier_before' => ['tier' => $before, 'name' => LeagueService::TIERS[$before]['name'], 'color' => LeagueService::TIERS[$before]['color']],
                'tier_after' => ['tier' => $after, 'name' => LeagueService::TIERS[$after]['name'], 'color' => LeagueService::TIERS[$after]['color']],
            ],
        ]);
    }

    /**
     * Mark every unseen result for the player as seen, so the overview shows once.
     */
    public function markLastResultSeen(Request $request): JsonResponse
    {
        LeagueResult::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('seen_at')
            ->update(['seen_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
