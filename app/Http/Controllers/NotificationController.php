<?php

namespace App\Http\Controllers;

use App\Models\UserNotification;
use App\Models\UserUnlockable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = UserNotification::where('user_id', $request->user()->id)
            ->notDeleted()
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = UserNotification::where('user_id', $request->user()->id)
            ->notDeleted()
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markRead(Request $request, UserNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not authorized'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json($notification);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        UserNotification::where('user_id', $request->user()->id)
            ->notDeleted()
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    public function claim(Request $request, UserNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not authorized'], 403);
        }

        $user = $request->user();
        $data = $notification->data ?? [];
        $alreadyClaimed = (bool) $notification->claimed_at;

        // XP and coins are only granted once
        if (!$alreadyClaimed) {
            // Grant XP
            $rewardXp = $data['reward_xp'] ?? 0;
            if ($rewardXp > 0) {
                $user->xp += $rewardXp;
                $user->level = \App\Models\User::calculateLevel($user->xp);
            }

            // Grant coins
            $rewardCoins = $data['reward_coins'] ?? 0;
            if ($rewardCoins > 0) {
                $user->coins += $rewardCoins;
            }

            $user->save();

            // Record coin transaction
            if ($rewardCoins > 0) {
                $source = $notification->type === 'season_reward' ? 'season_reward' : 'admin_gift';
                $user->recordCoinTransaction($rewardCoins, 'earn', $source, $notification->id, $notification->title);
            }
        }

        // Unlockable grants are idempotent — always attempt them
        // Grant character unlockable
        $characterId = $data['reward_character_id'] ?? null;
        if ($characterId) {
            $unlockable = \App\Models\Unlockable::firstOrCreate(
                ['type' => 'character', 'entity_id' => $characterId],
                ['unlock_method' => 'gift', 'unlock_value' => 0],
            );

            UserUnlockable::firstOrCreate(
                ['user_id' => $user->id, 'unlockable_id' => $unlockable->id],
                ['unlocked_at' => now()],
            );
        }

        // Grant dice theme unlockable
        $diceThemeId = $data['reward_dice_theme_id'] ?? null;
        if ($diceThemeId) {
            $unlockable = \App\Models\Unlockable::firstOrCreate(
                ['type' => 'dice_theme', 'entity_id' => $diceThemeId],
                ['unlock_method' => 'gift', 'unlock_value' => 0],
            );

            UserUnlockable::firstOrCreate(
                ['user_id' => $user->id, 'unlockable_id' => $unlockable->id],
                ['unlocked_at' => now()],
            );
        }

        // Mark as claimed and read
        $notification->update([
            'claimed_at' => now(),
            'read_at' => $notification->read_at ?? now(),
        ]);

        return response()->json([
            'notification' => $notification->fresh(),
            'user' => [
                'xp' => $user->xp,
                'level' => $user->level,
                'coins' => $user->coins,
            ],
        ]);
    }

    public function destroy(Request $request, UserNotification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Not authorized'], 403);
        }

        $notification->update(['deleted_at' => now(), 'read_at' => $notification->read_at ?? now()]);

        return response()->json(null, 204);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $allowed = ['push_game', 'push_social', 'push_achievement', 'push_season', 'push_admin', 'push_challenge'];

        $preferences = $request->validate([
            'preferences' => 'required|array',
        ]);

        $filtered = [];
        foreach ($preferences['preferences'] as $key => $value) {
            if (in_array($key, $allowed)) {
                $filtered[$key] = (bool) $value;
            }
        }

        $user = $request->user();
        $user->notification_preferences = $filtered;
        $user->save();

        return response()->json(['notification_preferences' => $user->notification_preferences]);
    }
}
