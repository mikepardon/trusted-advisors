<?php

namespace App\Http\Controllers;

use App\Events\UserNotificationReceived;
use App\Models\Character;
use App\Models\CoinTransaction;
use App\Models\DiceTheme;
use App\Models\Friendship;
use App\Models\Item;
use App\Models\KingdomStyle;
use App\Models\Unlockable;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\UserCharacter;
use App\Models\UserUnlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CoinShopController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Get coin-purchasable items AND cash-priced items
        $unlockables = Unlockable::where('unlock_method', 'coins')
            ->orWhereNotNull('cash_price_cents')
            ->get();

        $ownedIds = UserUnlockable::where('user_id', $user->id)
            ->pluck('unlockable_id')
            ->toArray();

        // Pre-load entity details
        $characterIds = $unlockables->where('type', 'character')->pluck('entity_id')->unique();
        $itemIds = $unlockables->where('type', 'item')->pluck('entity_id')->unique();
        $diceThemeIds = $unlockables->where('type', 'dice_theme')->pluck('entity_id')->unique();
        $kingdomStyleIds = $unlockables->where('type', 'kingdom_style')->pluck('entity_id')->unique();
        $characters = Character::whereIn('id', $characterIds)->get()->keyBy('id');
        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');
        $diceThemes = DiceTheme::whereIn('id', $diceThemeIds)->get()->keyBy('id');
        $kingdomStyles = KingdomStyle::whereIn('id', $kingdomStyleIds)->get()->keyBy('id');

        $shopItems = $unlockables->map(function ($unlockable) use ($ownedIds, $characters, $items, $diceThemes, $kingdomStyles) {
            $entity = match ($unlockable->type) {
                'character' => $characters[$unlockable->entity_id] ?? null,
                'dice_theme' => $diceThemes[$unlockable->entity_id] ?? null,
                'kingdom_style' => $kingdomStyles[$unlockable->entity_id] ?? null,
                default => $items[$unlockable->entity_id] ?? null,
            };

            $item = [
                'id' => $unlockable->id,
                'type' => $unlockable->type,
                'entity_id' => $unlockable->entity_id,
                'name' => $entity?->name ?? 'Unknown',
                'description' => $entity?->description ?? '',
                'image_url' => $entity?->image_url ?? null,
                'price' => $unlockable->unlock_method === 'coins' ? (int) $unlockable->unlock_value : null,
                'cash_price_cents' => $unlockable->cash_price_cents,
                'stripe_price_id' => $unlockable->stripe_price_id,
                'apple_product_id' => $unlockable->apple_product_id,
                'google_product_id' => $unlockable->google_product_id,
                'owned' => in_array($unlockable->id, $ownedIds),
            ];

            // Include type-specific entity details for previews
            if ($unlockable->type === 'character' && $entity) {
                $item['dice'] = $entity->dice;
                $item['wild_value'] = $entity->wild_value;
                $item['wild_ability'] = $entity->wild_ability;
                $item['wild_ability_description'] = $entity->wild_ability_description;
            } elseif ($unlockable->type === 'dice_theme' && $entity) {
                $item['slug'] = $entity->slug;
                $item['preview_image'] = $entity->preview_image;
            } elseif ($unlockable->type === 'kingdom_style' && $entity) {
                $item['slug'] = $entity->slug;
                $item['css_vars'] = $entity->css_vars;
                $item['background_image_url'] = $entity->background_image_url;
            }

            return $item;
        });

        return response()->json([
            'items' => $shopItems,
            'coins' => $user->coins,
            'is_premium' => $user->isPremium(),
            'premium_product' => [
                'stripe_price_id' => config('services.stripe.premium_price_id'),
                'apple_product_id' => 'com.trustedadvisors.premium',
                'google_product_id' => 'com.trustedadvisors.premium',
            ],
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

        $entityName = match ($unlockable->type) {
            'character' => Character::find($unlockable->entity_id)?->name ?? 'Unknown',
            'dice_theme' => DiceTheme::find($unlockable->entity_id)?->name ?? 'Unknown',
            'kingdom_style' => KingdomStyle::find($unlockable->entity_id)?->name ?? 'Unknown',
            default => Item::find($unlockable->entity_id)?->name ?? 'Unknown',
        };

        $user->recordCoinTransaction(-$price, 'spend', 'shop', $unlockable->id, "Purchased {$unlockable->type}: {$entityName}");

        UserUnlockable::create([
            'user_id' => $user->id,
            'unlockable_id' => $unlockable->id,
            'unlocked_at' => now(),
        ]);

        // Auto-create UserCharacter when a character is purchased
        if ($unlockable->type === 'character' && $unlockable->entity_id) {
            UserCharacter::firstOrCreate([
                'user_id' => $user->id,
                'character_id' => $unlockable->entity_id,
            ]);
        }

        return response()->json([
            'message' => 'Purchase successful!',
            'new_coins' => $user->coins,
        ]);
    }

    public function gift(Request $request, Unlockable $unlockable): JsonResponse
    {
        $request->validate(['friend_id' => 'required|integer']);

        $user = $request->user();
        $friendId = $request->friend_id;

        // Verify friendship
        $areFriends = Friendship::where('status', 'accepted')
            ->where(function ($q) use ($user, $friendId) {
                $q->where(function ($q2) use ($user, $friendId) {
                    $q2->where('sender_id', $user->id)->where('receiver_id', $friendId);
                })->orWhere(function ($q2) use ($user, $friendId) {
                    $q2->where('sender_id', $friendId)->where('receiver_id', $user->id);
                });
            })
            ->exists();

        if (!$areFriends) {
            return response()->json(['error' => 'You can only gift to friends.'], 422);
        }

        if ($unlockable->unlock_method !== 'coins') {
            return response()->json(['error' => 'This item is not available for coin purchase.'], 422);
        }

        $price = (int) $unlockable->unlock_value;

        if ($user->coins < $price) {
            return response()->json(['error' => 'Not enough coins.'], 422);
        }

        $recipient = User::find($friendId);
        if (!$recipient) {
            return response()->json(['error' => 'User not found.'], 404);
        }

        $alreadyOwned = UserUnlockable::where('user_id', $recipient->id)
            ->where('unlockable_id', $unlockable->id)
            ->exists();

        if ($alreadyOwned) {
            return response()->json(['error' => 'Your friend already owns this item.'], 409);
        }

        $entityName = match ($unlockable->type) {
            'character' => Character::find($unlockable->entity_id)?->name ?? 'Unknown',
            'dice_theme' => DiceTheme::find($unlockable->entity_id)?->name ?? 'Unknown',
            'kingdom_style' => KingdomStyle::find($unlockable->entity_id)?->name ?? 'Unknown',
            default => Item::find($unlockable->entity_id)?->name ?? 'Unknown',
        };

        $user->coins -= $price;
        $user->save();

        $user->recordCoinTransaction(-$price, 'spend', 'shop', $unlockable->id, "Gifted {$unlockable->type}: {$entityName} to {$recipient->name}");

        UserUnlockable::create([
            'user_id' => $recipient->id,
            'unlockable_id' => $unlockable->id,
            'unlocked_at' => now(),
        ]);

        // Auto-create UserCharacter when a character is gifted
        if ($unlockable->type === 'character' && $unlockable->entity_id) {
            UserCharacter::firstOrCreate([
                'user_id' => $recipient->id,
                'character_id' => $unlockable->entity_id,
            ]);
        }

        $notification = UserNotification::create([
            'user_id' => $recipient->id,
            'type' => 'gift_received',
            'title' => "Gift from {$user->name}!",
            'message' => "{$user->name} gifted you {$entityName}!",
            'data' => [
                'gifter_id' => $user->id,
                'gifter_name' => $user->name,
                'unlockable_id' => $unlockable->id,
                'item_name' => $entityName,
                'item_type' => $unlockable->type,
            ],
        ]);

        try {
            broadcast(new UserNotificationReceived(
                $recipient->id,
                $notification->id,
                'gift_received',
                $notification->title,
            ));
        } catch (\Throwable) {
            // Broadcast failure is non-critical; notification is saved in DB
        }

        return response()->json([
            'message' => "Gift sent to {$recipient->name}!",
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
