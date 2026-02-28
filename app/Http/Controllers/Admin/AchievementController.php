<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Achievement::orderBy('category')->orderBy('name')->get());
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'required|string|max:255|unique:achievements,key',
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'icon' => 'nullable|string|max:255',
            'category' => 'sometimes|string|max:255',
            'criteria' => 'required|array',
            'criteria.type' => 'required|string',
            'reward_type' => 'nullable|string|in:unlockable',
            'reward_id' => 'nullable|integer',
        ]);

        $achievement = Achievement::create($validated);
        return response()->json($achievement, 201);
    }

    public function show(Achievement $achievement): JsonResponse
    {
        return response()->json($achievement);
    }

    public function update(Request $request, Achievement $achievement): JsonResponse
    {
        $validated = $request->validate([
            'key' => 'sometimes|string|max:255|unique:achievements,key,' . $achievement->id,
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'icon' => 'nullable|string|max:255',
            'category' => 'sometimes|string|max:255',
            'criteria' => 'sometimes|array',
            'criteria.type' => 'required_with:criteria|string',
            'reward_type' => 'nullable|string|in:unlockable',
            'reward_id' => 'nullable|integer',
        ]);

        $achievement->update($validated);
        return response()->json($achievement);
    }

    public function destroy(Achievement $achievement): JsonResponse
    {
        $achievement->delete();
        return response()->json(null, 204);
    }
}
