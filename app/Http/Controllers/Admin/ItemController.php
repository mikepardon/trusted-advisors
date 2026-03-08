<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemController extends Controller
{
    use AuditsAdminActions;
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
            'effect_type' => 'required|string|in:passive,active,immediate',
            'is_negative' => 'boolean',
            'is_consumable' => 'boolean',
            'target' => 'nullable|string|in:opponent',
            'addon_id' => 'nullable|integer|exists:addons,id',
            'available_cooperative' => 'boolean',
            'available_duel' => 'boolean',
            'effect_duel' => 'nullable|array',
        ]);

        $item = Item::create($validated);
        $this->auditLog('create', 'Item', $item->id);

        return response()->json($item, 201);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'effect' => 'required|array',
            'effect_type' => 'required|string|in:passive,active,immediate',
            'is_negative' => 'boolean',
            'is_consumable' => 'boolean',
            'target' => 'nullable|string|in:opponent',
            'addon_id' => 'nullable|integer|exists:addons,id',
            'available_cooperative' => 'boolean',
            'available_duel' => 'boolean',
            'effect_duel' => 'nullable|array',
        ]);

        $old = $item->only(array_keys($validated));
        $item->update($validated);
        $this->auditModelChange('update', $item, $old);

        return response()->json($item);
    }

    public function destroy(Item $item): JsonResponse
    {
        $this->auditLog('delete', 'Item', $item->id, null, ['name' => $item->name]);
        $item->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
