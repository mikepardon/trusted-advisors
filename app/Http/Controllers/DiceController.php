<?php

namespace App\Http\Controllers;

use App\Models\DiceTheme;
use App\Models\Unlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiceController extends Controller
{
    public function myDice(Request $request): JsonResponse
    {
        $user = $request->user();

        // Default-unlocked active themes
        $defaultThemes = DiceTheme::where('is_default_unlocked', true)
            ->where('is_active', true)
            ->get();

        // Themes unlocked via unlockable system
        $unlockedThemeIds = Unlockable::where('type', 'dice_theme')
            ->whereHas('userUnlockables', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->pluck('entity_id');

        $unlockedThemes = DiceTheme::whereIn('id', $unlockedThemeIds)
            ->where('is_active', true)
            ->get();

        // Merge and deduplicate
        $allThemes = $defaultThemes->merge($unlockedThemes)->unique('id')->values();

        $activeSlug = $user->active_dice_theme_slug;

        // Default to dice theme ID 1 if user hasn't selected one
        if (!$activeSlug) {
            $defaultTheme = $allThemes->firstWhere('id', 1);
            $activeSlug = $defaultTheme?->slug;
        }

        return response()->json($allThemes->map(function ($theme) use ($activeSlug) {
            return [
                'id' => $theme->id,
                'slug' => $theme->slug,
                'name' => $theme->name,
                'description' => $theme->description,
                'preview_image' => $theme->preview_image,
                'is_active_selection' => $theme->slug === $activeSlug,
            ];
        }));
    }

    public function activate(Request $request, DiceTheme $diceTheme): JsonResponse
    {
        $user = $request->user();

        // Verify user has access: either default-unlocked or owned via unlockable
        $hasAccess = false;

        if ($diceTheme->is_active && $diceTheme->is_default_unlocked) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            $hasAccess = Unlockable::where('type', 'dice_theme')
                ->where('entity_id', $diceTheme->id)
                ->whereHas('userUnlockables', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->exists();
        }

        if (!$hasAccess) {
            return response()->json(['error' => 'You do not have access to this dice theme.'], 403);
        }

        $user->active_dice_theme_slug = $diceTheme->slug;
        $user->save();

        return $this->myDice($request);
    }
}
