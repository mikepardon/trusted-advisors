<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CharacterLevelOption;
use App\Models\UserCharacter;
use App\Traits\AuditsAdminActions;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CharacterLevelOptionController extends Controller
{
    use AuditsAdminActions;

    public function index(): JsonResponse
    {
        $options = CharacterLevelOption::with('character:id,name')
            ->orderBy('available_at_level')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($options);
    }

    public function show(CharacterLevelOption $characterLevelOption): JsonResponse
    {
        return response()->json($characterLevelOption->load('character:id,name'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:bump_dice_face,bump_two_dice_faces,start_with_item,start_with_curse,extra_item_slot,passive_stat_bonus,add_wild,card_redraw',
            'config' => 'nullable|array',
            'available_at_level' => 'required|integer|min:1|max:' . (UserCharacter::getMaxLevel() - 1),
            'character_id' => 'nullable|integer|exists:characters,id',
            'is_active' => 'boolean',
            'max_selections' => 'integer|min:0',
            'sort_order' => 'integer|min:0',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
        ]);

        $option = CharacterLevelOption::create($validated);
        $this->auditLog('create', 'CharacterLevelOption', $option->id);

        return response()->json($option, 201);
    }

    public function update(Request $request, CharacterLevelOption $characterLevelOption): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|string|in:bump_dice_face,bump_two_dice_faces,start_with_item,start_with_curse,extra_item_slot,passive_stat_bonus,add_wild,card_redraw',
            'config' => 'nullable|array',
            'available_at_level' => 'required|integer|min:1|max:' . (UserCharacter::getMaxLevel() - 1),
            'character_id' => 'nullable|integer|exists:characters,id',
            'is_active' => 'boolean',
            'max_selections' => 'integer|min:0',
            'sort_order' => 'integer|min:0',
            'description' => 'nullable|string|max:500',
            'icon' => 'nullable|string|max:100',
        ]);

        $old = $characterLevelOption->only(array_keys($validated));
        $characterLevelOption->update($validated);
        $this->auditModelChange('update', $characterLevelOption, $old);

        return response()->json($characterLevelOption);
    }

    public function destroy(CharacterLevelOption $characterLevelOption): JsonResponse
    {
        $this->auditLog('delete', 'CharacterLevelOption', $characterLevelOption->id, null, ['name' => $characterLevelOption->name]);
        $characterLevelOption->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
