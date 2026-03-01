<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DailyChallenge;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DailyChallengeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DailyChallenge::orderByDesc('date')->limit(60)->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|unique:daily_challenges,date',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'criteria' => 'required|array',
            'criteria.type' => 'required|string',
            'reward_xp' => 'sometimes|integer|min:0',
            'is_manual' => 'boolean',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $validated['is_manual'] = true;
        $challenge = DailyChallenge::create($validated);
        return response()->json($challenge, 201);
    }

    public function show(DailyChallenge $dailyChallenge): JsonResponse
    {
        return response()->json($dailyChallenge);
    }

    public function update(Request $request, DailyChallenge $dailyChallenge): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'sometimes|date|unique:daily_challenges,date,' . $dailyChallenge->id,
            'title' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'criteria' => 'sometimes|array',
            'criteria.type' => 'required_with:criteria|string',
            'reward_xp' => 'sometimes|integer|min:0',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $dailyChallenge->update($validated);
        return response()->json($dailyChallenge);
    }

    public function destroy(DailyChallenge $dailyChallenge): JsonResponse
    {
        $dailyChallenge->delete();
        return response()->json(null, 204);
    }
}
