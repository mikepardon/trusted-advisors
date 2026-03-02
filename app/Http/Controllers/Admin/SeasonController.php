<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Season;
use App\Models\SeasonReward;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SeasonController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Season::orderByDesc('starts_at')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        // Check for overlapping seasons
        $overlap = Season::where(function ($q) use ($validated) {
            $q->where('starts_at', '<', $validated['ends_at'])
              ->where('ends_at', '>', $validated['starts_at']);
        })->exists();

        if ($overlap) {
            return response()->json(['error' => 'Season dates overlap with an existing season'], 422);
        }

        // Only one active season at a time
        if (!empty($validated['is_active'])) {
            Season::where('is_active', true)->update(['is_active' => false]);
        }

        $season = Season::create($validated);
        return response()->json($season, 201);
    }

    public function show(Season $season): JsonResponse
    {
        return response()->json($season);
    }

    public function update(Request $request, Season $season): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'starts_at' => 'sometimes|date',
            'ends_at' => 'sometimes|date|after:starts_at',
            'is_active' => 'boolean',
        ]);

        // Check for overlapping seasons (excluding self)
        if (isset($validated['starts_at']) || isset($validated['ends_at'])) {
            $startsAt = $validated['starts_at'] ?? $season->starts_at;
            $endsAt = $validated['ends_at'] ?? $season->ends_at;

            $overlap = Season::where('id', '!=', $season->id)
                ->where(function ($q) use ($startsAt, $endsAt) {
                    $q->where('starts_at', '<', $endsAt)
                      ->where('ends_at', '>', $startsAt);
                })->exists();

            if ($overlap) {
                return response()->json(['error' => 'Season dates overlap with an existing season'], 422);
            }
        }

        // Only one active season at a time
        if (!empty($validated['is_active'])) {
            Season::where('id', '!=', $season->id)->where('is_active', true)->update(['is_active' => false]);
        }

        $season->update($validated);
        return response()->json($season);
    }

    public function destroy(Season $season): JsonResponse
    {
        $season->delete();
        return response()->json(null, 204);
    }

    // Season Rewards CRUD

    public function rewards(Season $season): JsonResponse
    {
        $rewards = $season->rewards()->with('rewardCharacter')->orderBy('placement')->get();
        return response()->json($rewards);
    }

    public function storeReward(Request $request, Season $season): JsonResponse
    {
        $validated = $request->validate([
            'metric' => 'sometimes|string|in:elo,score,wins',
            'placement' => 'required|integer|min:1',
            'reward_xp' => 'integer|min:0',
            'reward_coins' => 'integer|min:0',
            'reward_character_id' => 'nullable|exists:characters,id',
            'reward_title' => 'nullable|string|max:255',
        ]);

        $validated['season_id'] = $season->id;
        $reward = SeasonReward::create($validated);
        $reward->load('rewardCharacter');

        return response()->json($reward, 201);
    }

    public function updateReward(Request $request, Season $season, SeasonReward $reward): JsonResponse
    {
        if ($reward->season_id !== $season->id) {
            return response()->json(['error' => 'Reward does not belong to this season.'], 404);
        }

        $validated = $request->validate([
            'metric' => 'sometimes|string|in:elo,score,wins',
            'placement' => 'sometimes|integer|min:1',
            'reward_xp' => 'integer|min:0',
            'reward_coins' => 'integer|min:0',
            'reward_character_id' => 'nullable|exists:characters,id',
            'reward_title' => 'nullable|string|max:255',
        ]);

        $reward->update($validated);
        $reward->load('rewardCharacter');

        return response()->json($reward);
    }

    public function destroyReward(Season $season, SeasonReward $reward): JsonResponse
    {
        if ($reward->season_id !== $season->id) {
            return response()->json(['error' => 'Reward does not belong to this season.'], 404);
        }

        $reward->delete();
        return response()->json(null, 204);
    }
}
