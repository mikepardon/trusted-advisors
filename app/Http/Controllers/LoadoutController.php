<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoadoutController extends Controller
{
    private const MAX_EQUIPPED = 3;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $ownedIds = $user->ownedItemIds();
        $equippedIds = $user->equippedItemIds();

        $items = Item::query()
            ->whereIn('id', $ownedIds)
            ->orderBy('type')
            ->orderBy('name')
            ->get()
            ->map(fn (Item $item): array => $this->present($item, in_array($item->id, $equippedIds, true)))
            ->values();

        return response()->json([
            'items' => $items,
            'equipped' => $equippedIds,
            'max_equipped' => self::MAX_EQUIPPED,
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_ids' => ['present', 'array', 'max:'.self::MAX_EQUIPPED],
            'item_ids.*' => ['integer'],
        ]);

        $user = $request->user();

        // Reject-by-default: every requested id must be a distinct item the user owns.
        $requestedIds = collect($validated['item_ids'])->map(fn ($id): int => (int) $id)->unique()->values();

        if ($requestedIds->count() > self::MAX_EQUIPPED) {
            return response()->json(['message' => 'You can equip at most '.self::MAX_EQUIPPED.' items.'], 422);
        }

        $ownedIds = $user->ownedItemIds();
        $notOwned = $requestedIds->reject(fn (int $id): bool => in_array($id, $ownedIds, true));

        if ($notOwned->isNotEmpty()) {
            return response()->json(['message' => 'You have not unlocked one or more of those items.'], 403);
        }

        DB::transaction(function () use ($user, $requestedIds): void {
            // Clear the current loadout, then equip the chosen items in slot order.
            $user->items()->update(['equipped' => false, 'slot' => null]);

            $requestedIds->values()->each(function (int $itemId, int $index) use ($user): void {
                $user->items()->updateOrCreate(
                    ['item_id' => $itemId],
                    ['equipped' => true, 'slot' => $index + 1],
                );
            });
        });

        return response()->json([
            'equipped' => $user->equippedItemIds(),
            'max_equipped' => self::MAX_EQUIPPED,
        ]);
    }

    /**
     * @return array{id: int, name: string, description: string|null, type: string|null, icon_key: string|null, cadence: string, target: string|null, is_negative: bool, available_cooperative: bool, available_duel: bool, effect: array<string, mixed>|null, effect_duel: array<string, mixed>|null, equipped: bool}
     */
    private function present(Item $item, bool $equipped): array
    {
        return [
            'id' => $item->id,
            'name' => $item->name,
            'description' => $item->description,
            'type' => $item->type,
            'icon_key' => $item->icon_key,
            'cadence' => $item->cadence,
            'rarity' => $item->rarity,
            'target' => $item->target,
            'is_negative' => (bool) $item->is_negative,
            'available_cooperative' => (bool) $item->available_cooperative,
            'available_duel' => (bool) $item->available_duel,
            'effect' => $item->effect,
            'effect_duel' => $item->effect_duel,
            'equipped' => $equipped,
        ];
    }
}
