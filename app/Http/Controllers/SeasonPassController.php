<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\SeasonPassTier;
use App\Services\SeasonPassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonPassController extends Controller
{
    public function __construct(private readonly SeasonPassService $passService) {}

    public function index(Request $request): JsonResponse
    {
        $season = $this->passService->activeSeason();

        if ($season === null) {
            return response()->json(['season' => null]);
        }

        return response()->json($this->passService->state($request->user(), $season));
    }

    public function claim(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tier' => ['required', 'integer', 'min:1'],
        ]);

        $season = $this->passService->activeSeason();

        if ($season === null) {
            return response()->json(['message' => 'There is no active Season Pass.'], 422);
        }

        $tier = SeasonPassTier::query()
            ->where('season_id', $season->id)
            ->where('tier', $validated['tier'])
            ->with('rewardCosmetic')
            ->first();

        if ($tier === null) {
            return response()->json(['message' => 'That tier does not exist.'], 404);
        }

        $progress = $this->passService->progressFor($request->user(), $season);

        if ($progress->points < $tier->points_required) {
            return response()->json(['message' => 'You have not reached that tier yet.'], 422);
        }

        if (in_array($tier->tier, $progress->claimed_tiers ?? [], true)) {
            return response()->json(['message' => 'You have already claimed that tier.'], 422);
        }

        $granted = $this->passService->grantTier($request->user(), $season, $tier);

        // A concurrent request may have claimed this tier between the check above
        // and the locked grant; the service reports that rather than double-granting.
        if ($granted['alreadyClaimed']) {
            return response()->json(['message' => 'You have already claimed that tier.'], 422);
        }

        return response()->json([
            'granted' => ['coins' => $granted['coins'], 'cosmetic' => $granted['cosmetic'], 'item' => $granted['item']],
            'state' => $this->passService->state($request->user(), $season),
        ]);
    }
}
