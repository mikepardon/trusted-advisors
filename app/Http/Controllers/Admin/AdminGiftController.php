<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendAdminGiftToUsers;
use App\Models\AdminGift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminGiftController extends Controller
{
    public function index(): JsonResponse
    {
        $gifts = AdminGift::with('creator:id,name', 'rewardCharacter:id,name', 'rewardDiceTheme:id,name,slug', 'rewardKingdomStyle:id,name,slug')
            ->orderByDesc('created_at')
            ->get();

        return response()->json($gifts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'note' => 'nullable|string',
            'reward_xp' => 'integer|min:0',
            'reward_coins' => 'integer|min:0',
            'reward_character_id' => 'nullable|exists:characters,id',
            'reward_dice_theme_id' => 'nullable|exists:dice_themes,id',
            'reward_kingdom_style_id' => 'nullable|exists:kingdom_styles,id',
        ]);

        // At least one reward must be set
        if (($validated['reward_xp'] ?? 0) === 0 && ($validated['reward_coins'] ?? 0) === 0 && empty($validated['reward_character_id']) && empty($validated['reward_dice_theme_id']) && empty($validated['reward_kingdom_style_id'])) {
            return response()->json(['error' => 'At least one reward must be specified.'], 422);
        }

        $gift = AdminGift::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'recipient_count' => 0,
        ]);

        SendAdminGiftToUsers::dispatchSync($gift, $validated);

        return response()->json($gift->fresh()->load('creator:id,name', 'rewardCharacter:id,name', 'rewardDiceTheme:id,name,slug', 'rewardKingdomStyle:id,name,slug'), 201);
    }
}
