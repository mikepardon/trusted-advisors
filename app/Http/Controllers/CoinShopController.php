<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\CoinTransaction;
use App\Models\Item;
use App\Models\Unlockable;
use App\Models\UserUnlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoinShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $unlockables = Unlockable::where('unlock_method', 'coins')->get();

        $ownedIds = UserUnlockable::where('user_id', $user->id)
            ->pluck('unlockable_id')
            ->toArray();

        // Pre-load entity details
        $characterIds = $unlockables->where('type', 'character')->pluck('entity_id')->unique();
        $itemIds = $unlockables->where('type', 'item')->pluck('entity_id')->unique();
        $characters = Character::whereIn('id', $characterIds)->get()->keyBy('id');
        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        $shopItems = $unlockables->map(function ($unlockable) use ($ownedIds, $characters, $items) {
            $entity = $unlockable->type === 'character'
                ? ($characters[$unlockable->entity_id] ?? null)
                : ($items[$unlockable->entity_id] ?? null);

            return [
                'id' => $unlockable->id,
                'type' => $unlockable->type,
                'entity_id' => $unlockable->entity_id,
                'name' => $entity?->name ?? 'Unknown',
                'description' => $entity?->description ?? '',
                'image_url' => $entity?->image_url ?? null,
                'price' => (int) $unlockable->unlock_value,
                'owned' => in_array($unlockable->id, $ownedIds),
            ];
        });

        return response()->json([
            'items' => $shopItems,
            'coins' => $user->coins,
        ]);
    }

    public function purchase(Request $request, Unlockable $unlockable): JsonResponse
    {
        $user = $request->user();

        if ($unlockable->unlock_method !== 'coins') {
            return response()->json(['error' => 'This item is not available for coin purchase.'], 422);
        }

        $price = (int) $unlockable->unlock_value;

        if ($user->coins < $price) {
            return response()->json(['error' => 'Not enough coins.'], 422);
        }

        $alreadyOwned = UserUnlockable::where('user_id', $user->id)
            ->where('unlockable_id', $unlockable->id)
            ->exists();

        if ($alreadyOwned) {
            return response()->json(['error' => 'Already owned.'], 409);
        }

        $user->coins -= $price;
        $user->save();

        $entityName = $unlockable->type === 'character'
            ? (Character::find($unlockable->entity_id)?->name ?? 'Unknown')
            : (Item::find($unlockable->entity_id)?->name ?? 'Unknown');

        $user->recordCoinTransaction(-$price, 'spend', 'shop', $unlockable->id, "Purchased {$unlockable->type}: {$entityName}");

        UserUnlockable::create([
            'user_id' => $user->id,
            'unlockable_id' => $unlockable->id,
            'unlocked_at' => now(),
        ]);

        return response()->json([
            'message' => 'Purchase successful!',
            'new_coins' => $user->coins,
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $transactions = CoinTransaction::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        return response()->json(['transactions' => $transactions]);
    }
}
