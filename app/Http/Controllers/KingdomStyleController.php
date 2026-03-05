<?php

namespace App\Http\Controllers;

use App\Models\KingdomStyle;
use App\Models\Unlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KingdomStyleController extends Controller
{
    public function myStyles(Request $request): JsonResponse
    {
        $user = $request->user();

        // Default-unlocked active styles
        $defaultStyles = KingdomStyle::where('is_default_unlocked', true)
            ->where('is_active', true)
            ->get();

        // Styles unlocked via unlockable system
        $unlockedStyleIds = Unlockable::where('type', 'kingdom_style')
            ->whereHas('userUnlockables', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->pluck('entity_id');

        $unlockedStyles = KingdomStyle::whereIn('id', $unlockedStyleIds)
            ->where('is_active', true)
            ->get();

        // Merge and deduplicate
        $allStyles = $defaultStyles->merge($unlockedStyles)->unique('id')->values();

        $activeSlug = $user->active_kingdom_style_slug;

        return response()->json($allStyles->map(function ($style) use ($activeSlug) {
            return [
                'id' => $style->id,
                'slug' => $style->slug,
                'name' => $style->name,
                'description' => $style->description,
                'css_vars' => $style->css_vars,
                'background_image_url' => $style->background_image_url,
                'is_active_selection' => $style->slug === $activeSlug,
            ];
        }));
    }

    public function activate(Request $request, KingdomStyle $kingdomStyle): JsonResponse
    {
        $user = $request->user();

        // Verify user has access: either default-unlocked or owned via unlockable
        $hasAccess = false;

        if ($kingdomStyle->is_active && $kingdomStyle->is_default_unlocked) {
            $hasAccess = true;
        }

        if (!$hasAccess) {
            $hasAccess = Unlockable::where('type', 'kingdom_style')
                ->where('entity_id', $kingdomStyle->id)
                ->whereHas('userUnlockables', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                })
                ->exists();
        }

        if (!$hasAccess) {
            return response()->json(['error' => 'You do not have access to this kingdom style.'], 403);
        }

        $user->active_kingdom_style_slug = $kingdomStyle->slug;
        $user->save();

        return $this->myStyles($request);
    }

    public function setTitle(Request $request): JsonResponse
    {
        $request->validate([
            'title' => 'nullable|string|max:50',
        ]);

        $user = $request->user();
        $user->active_title = $request->input('title');
        $user->save();

        return response()->json(['active_title' => $user->active_title]);
    }
}
