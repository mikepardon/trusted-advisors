<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Cosmetic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CosmeticController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownedIds = $user->cosmetics()->pluck('cosmetics.id')->all();

        $cosmetics = Cosmetic::query()
            ->where('is_available', true)
            ->orderBy('type')
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(function (Cosmetic $cosmetic) use ($user, $ownedIds): array {
                return [
                    'id' => $cosmetic->id,
                    'type' => $cosmetic->type,
                    'slug' => $cosmetic->slug,
                    'name' => $cosmetic->name,
                    'rarity' => $cosmetic->rarity,
                    'value' => $cosmetic->value,
                    'meta' => $cosmetic->meta,
                    'owned' => $cosmetic->is_default || in_array($cosmetic->id, $ownedIds, true),
                    'active' => $this->isActive($user, $cosmetic),
                ];
            })
            ->values();

        return response()->json([
            'cosmetics' => $cosmetics,
            'active' => $this->activeMap($user),
        ]);
    }

    public function equip(Request $request, Cosmetic $cosmetic): JsonResponse
    {
        $user = $request->user();

        if (! $user->ownsCosmetic($cosmetic)) {
            return response()->json(['message' => 'You have not unlocked that item.'], 403);
        }

        $column = Cosmetic::SLOT_COLUMNS[$cosmetic->type] ?? null;

        if ($column !== null) {
            $user->{$column} = $cosmetic->slug;
            $user->save();

            return response()->json(['active' => $this->activeMap($user)]);
        }

        if (in_array($cosmetic->type, Cosmetic::CREST_TYPES, true)) {
            $crest = $user->crest_config ?? [];
            $crest[$cosmetic->type] = $cosmetic->slug;
            $user->crest_config = $crest;
            $user->save();

            return response()->json(['active' => $this->activeMap($user)]);
        }

        return response()->json(['message' => 'That item cannot be equipped.'], 422);
    }

    public function unequip(Request $request): JsonResponse
    {
        $validated = $request->validate(['type' => ['required', 'string']]);
        $type = $validated['type'];
        $user = $request->user();

        $column = Cosmetic::SLOT_COLUMNS[$type] ?? null;

        if ($column !== null) {
            $user->{$column} = null;
            $user->save();

            return response()->json(['active' => $this->activeMap($user)]);
        }

        if (in_array($type, Cosmetic::CREST_TYPES, true)) {
            $crest = $user->crest_config ?? [];
            unset($crest[$type]);
            $user->crest_config = $crest;
            $user->save();

            return response()->json(['active' => $this->activeMap($user)]);
        }

        return response()->json(['message' => 'Unknown cosmetic slot.'], 422);
    }

    private function isActive(User $user, Cosmetic $cosmetic): bool
    {
        $column = Cosmetic::SLOT_COLUMNS[$cosmetic->type] ?? null;

        if ($column !== null) {
            return $user->{$column} === $cosmetic->slug;
        }

        if (in_array($cosmetic->type, Cosmetic::CREST_TYPES, true)) {
            return ($user->crest_config[$cosmetic->type] ?? null) === $cosmetic->slug;
        }

        return false;
    }

    /**
     * @return array{title: string|null, frame: string|null, card_back: string|null, victory_fx: string|null, crest: array<string, string>}
     */
    private function activeMap(User $user): array
    {
        return [
            'title' => $user->active_title_slug,
            'frame' => $user->active_frame_slug,
            'card_back' => $user->active_card_back_slug,
            'victory_fx' => $user->active_victory_fx_slug,
            'crest' => $user->crest_config ?? [],
        ];
    }
}
