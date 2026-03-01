<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Addon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddonController extends Controller
{
    public function index(): JsonResponse
    {
        $addons = Addon::withCount(['characters', 'events', 'items', 'dailyChallenges'])
            ->orderBy('name')
            ->get();

        return response()->json($addons);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $addon = Addon::create($validated);
        return response()->json($addon, 201);
    }

    public function show(Addon $addon): JsonResponse
    {
        return response()->json($addon);
    }

    public function update(Request $request, Addon $addon): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'sometimes|boolean',
        ]);

        $addon->update($validated);
        return response()->json($addon);
    }

    public function destroy(Addon $addon): JsonResponse
    {
        $addon->delete();
        return response()->json(null, 204);
    }
}
