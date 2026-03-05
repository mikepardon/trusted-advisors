<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Achievement;
use App\Models\Character;
use App\Models\DiceTheme;
use App\Models\Item;
use App\Models\KingdomStyle;
use App\Models\Unlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnlockableController extends Controller
{
    public function index(): JsonResponse
    {
        $allUnlockables = Unlockable::orderBy('type')
            ->orderBy('unlock_method')
            ->get();

        // Resolve entity names manually (dynamic entity() relationship breaks eager loading)
        $characterIds = $allUnlockables->where('type', 'character')->pluck('entity_id')->unique();
        $itemIds = $allUnlockables->where('type', 'item')->pluck('entity_id')->unique();
        $diceThemeIds = $allUnlockables->where('type', 'dice_theme')->pluck('entity_id')->unique();
        $kingdomStyleIds = $allUnlockables->where('type', 'kingdom_style')->pluck('entity_id')->unique();
        $characterNames = Character::whereIn('id', $characterIds)->pluck('name', 'id');
        $itemNames = Item::whereIn('id', $itemIds)->pluck('name', 'id');
        $diceThemeNames = DiceTheme::whereIn('id', $diceThemeIds)->pluck('name', 'id');
        $kingdomStyleNames = KingdomStyle::whereIn('id', $kingdomStyleIds)->pluck('name', 'id');

        // Pre-load achievements for unlock_method=achievement
        $achIds = $allUnlockables->where('unlock_method', 'achievement')->pluck('unlock_value')->unique();
        $achNames = Achievement::whereIn('id', $achIds)->pluck('name', 'id');

        $unlockables = $allUnlockables->map(function ($u) use ($characterNames, $itemNames, $diceThemeNames, $kingdomStyleNames, $achNames) {
            if ($u->type === 'character') {
                $u->entity_name = $characterNames[$u->entity_id] ?? "#{$u->entity_id}";
            } elseif ($u->type === 'dice_theme') {
                $u->entity_name = $diceThemeNames[$u->entity_id] ?? "#{$u->entity_id}";
            } elseif ($u->type === 'kingdom_style') {
                $u->entity_name = $kingdomStyleNames[$u->entity_id] ?? "#{$u->entity_id}";
            } else {
                $u->entity_name = $itemNames[$u->entity_id] ?? "#{$u->entity_id}";
            }

            if ($u->unlock_method === 'achievement') {
                $u->unlock_label = $achNames[$u->unlock_value] ?? "Achievement #{$u->unlock_value}";
            } elseif ($u->unlock_method === 'coins') {
                $u->unlock_label = "{$u->unlock_value} coins";
            } else {
                $u->unlock_label = "Level {$u->unlock_value}";
            }

            return $u;
        });

        // Also return dropdown options
        $characters = Character::orderBy('name')->get(['id', 'name']);
        $items = Item::orderBy('name')->get(['id', 'name']);
        $achievements = Achievement::orderBy('name')->get(['id', 'name', 'key']);

        $diceThemes = DiceTheme::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $kingdomStyles = KingdomStyle::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return response()->json([
            'unlockables' => $unlockables,
            'characters' => $characters,
            'items' => $items,
            'dice_themes' => $diceThemes,
            'kingdom_styles' => $kingdomStyles,
            'achievements' => $achievements,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'required|string|in:character,item,dice_theme,kingdom_style',
            'entity_id' => 'required|integer',
            'unlock_method' => 'required|string|in:level,achievement,coins',
            'unlock_value' => 'required|integer|min:1',
            'cash_price_cents' => 'nullable|integer|min:0',
            'stripe_price_id' => 'nullable|string|max:255',
            'apple_product_id' => 'nullable|string|max:255',
            'google_product_id' => 'nullable|string|max:255',
        ]);

        $unlockable = Unlockable::create($validated);
        return response()->json($unlockable, 201);
    }

    public function show(Unlockable $unlockable): JsonResponse
    {
        return response()->json($unlockable);
    }

    public function update(Request $request, Unlockable $unlockable): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:character,item,dice_theme,kingdom_style',
            'entity_id' => 'sometimes|integer',
            'unlock_method' => 'sometimes|string|in:level,achievement,coins',
            'unlock_value' => 'sometimes|integer|min:1',
            'cash_price_cents' => 'nullable|integer|min:0',
            'stripe_price_id' => 'nullable|string|max:255',
            'apple_product_id' => 'nullable|string|max:255',
            'google_product_id' => 'nullable|string|max:255',
        ]);

        $unlockable->update($validated);
        return response()->json($unlockable);
    }

    public function destroy(Unlockable $unlockable): JsonResponse
    {
        $unlockable->delete();
        return response()->json(null, 204);
    }
}
