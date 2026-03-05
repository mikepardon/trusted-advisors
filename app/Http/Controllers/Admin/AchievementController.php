<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Character;
use App\Models\DiceTheme;
use App\Models\Item;
use App\Models\Unlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AchievementController extends Controller
{
    public function index(): JsonResponse
    {
        $achievements = Achievement::orderBy('category')->orderBy('tier_group')->orderBy('tier')->orderBy('name')->get();

        // Attach linked unlockables (those where unlock_method=achievement and unlock_value=achievement.id)
        $achievementIds = $achievements->pluck('id');
        $allUnlockables = Unlockable::where('unlock_method', 'achievement')
            ->whereIn('unlock_value', $achievementIds)
            ->get();

        // Resolve entity names manually (dynamic entity() relationship breaks eager loading)
        $characterIds = $allUnlockables->where('type', 'character')->pluck('entity_id')->unique();
        $itemIds = $allUnlockables->where('type', 'item')->pluck('entity_id')->unique();
        $diceThemeIds = $allUnlockables->where('type', 'dice_theme')->pluck('entity_id')->unique();
        $characterNames = Character::whereIn('id', $characterIds)->pluck('name', 'id');
        $itemNames = Item::whereIn('id', $itemIds)->pluck('name', 'id');
        $diceThemeNames = DiceTheme::whereIn('id', $diceThemeIds)->pluck('name', 'id');

        $grouped = $allUnlockables->groupBy('unlock_value');

        $achievements->each(function ($a) use ($grouped, $characterNames, $itemNames, $diceThemeNames) {
            $a->linked_unlockables = ($grouped[$a->id] ?? collect())->map(function ($u) use ($characterNames, $itemNames, $diceThemeNames) {
                $entityName = match ($u->type) {
                    'character' => $characterNames[$u->entity_id] ?? "#{$u->entity_id}",
                    'dice_theme' => $diceThemeNames[$u->entity_id] ?? "#{$u->entity_id}",
                    default => $itemNames[$u->entity_id] ?? "#{$u->entity_id}",
                };
                return [
                    'id' => $u->id,
                    'type' => $u->type,
                    'entity_id' => $u->entity_id,
                    'entity_name' => $entityName,
                ];
            })->values();
        });

        return response()->json($achievements);
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
            'reward_xp' => 'nullable|integer|min:0',
            'tier' => 'nullable|integer|min:1',
            'tier_group' => 'nullable|string|max:255',
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
            'reward_xp' => 'nullable|integer|min:0',
            'tier' => 'nullable|integer|min:1',
            'tier_group' => 'nullable|string|max:255',
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
