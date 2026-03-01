<?php

namespace App\Http\Controllers;

use App\Models\Unlockable;
use App\Models\UserUnlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoinShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $items = Unlockable::where('unlock_method', 'coins')->get();

        $ownedIds = UserUnlockable::where('user_id', $user->id)
            ->pluck('unlockable_id')
            ->toArray();

        $shopItems = $items->map(function ($item) use ($ownedIds) {
            return [
                'id' => $item->id,
                'type' => $item->type,
                'entity_id' => $item->entity_id,
                'price' => (int) $item->unlock_value,
                'owned' => in_array($item->id, $ownedIds),
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
}
