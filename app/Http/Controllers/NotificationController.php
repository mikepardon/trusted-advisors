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

        if ($notification->claimed_at) {
            return response()->json(['error' => 'Already claimed'], 422);
        }

        $user = $request->user();
        $data = $notification->data ?? [];

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

        // Grant character unlockable
        $characterId = $data['reward_character_id'] ?? null;
        if ($characterId) {
            $exists = UserUnlockable::where('user_id', $user->id)
                ->where('unlockable_id', $characterId)
                ->exists();

            if (!$exists) {
                // Find the unlockable linked to this character
                $unlockable = \App\Models\Unlockable::where('type', 'character')
                    ->where('reference_id', $characterId)
                    ->first();

                if ($unlockable) {
                    UserUnlockable::create([
                        'user_id' => $user->id,
                        'unlockable_id' => $unlockable->id,
                        'unlocked_at' => now(),
                    ]);
                }
            }
        }

        // Grant dice theme unlockable
        $diceThemeId = $data['reward_dice_theme_id'] ?? null;
        if ($diceThemeId) {
            $unlockable = \App\Models\Unlockable::where('type', 'dice_theme')
                ->where('entity_id', $diceThemeId)
                ->first();

            if ($unlockable) {
                $exists = UserUnlockable::where('user_id', $user->id)
                    ->where('unlockable_id', $unlockable->id)
                    ->exists();

                if (!$exists) {
                    UserUnlockable::create([
                        'user_id' => $user->id,
                        'unlockable_id' => $unlockable->id,
                        'unlocked_at' => now(),
                    ]);
                }
            }
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
