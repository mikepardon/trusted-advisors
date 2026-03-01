<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Item::orderBy('name')->get());
    }

    public function show(Item $item): JsonResponse
    {
        return response()->json($item);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'effect' => 'required|array',
            'effect_type' => 'required|string|in:passive,active',
            'is_negative' => 'boolean',
            'is_consumable' => 'boolean',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $item = Item::create($validated);

        return response()->json($item, 201);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'effect' => 'required|array',
            'effect_type' => 'required|string|in:passive,active',
            'is_negative' => 'boolean',
            'is_consumable' => 'boolean',
            'addon_id' => 'nullable|integer|exists:addons,id',
        ]);

        $item->update($validated);

        return response()->json($item);
    }

    public function destroy(Item $item): JsonResponse
    {
        $item->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
