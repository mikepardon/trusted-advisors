<?php

namespace App\Http\Controllers;

use App\Events\GameInviteReceived;
use App\Events\GameStarted;
use App\Events\PlayerJoinedGame;
use App\Events\PlayerSelectedCharacter;
use App\Models\Character;
use App\Models\Friendship;
use App\Models\Game;
use App\Models\GameInvite;
use App\Models\GamePlayer;
use App\Models\Unlockable;
use App\Models\User;
use App\Models\UserUnlockable;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class GameLobbyController extends Controller
{
    public function invite(Game $game, Request $request): JsonResponse
    {
        if ($game->user_id !== $request->user()->id) {
            return response()->json(['error' => 'Only the host can send invites'], 403);
        }

        if (!$game->isOnline() || $game->status !== 'setup') {
            return response()->json(['error' => 'Game is not accepting invites'], 422);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
        ]);

        $receiverId = $validated['user_id'];

        // Verify they are friends
        $areFriends = Friendship::where('status', 'accepted')
            ->where(function ($q) use ($request, $receiverId) {
                $q->where(function ($q2) use ($request, $receiverId) {
                    $q2->where('sender_id', $request->user()->id)->where('receiver_id', $receiverId);
                })->orWhere(function ($q2) use ($request, $receiverId) {
                    $q2->where('sender_id', $receiverId)->where('receiver_id', $request->user()->id);
                });
            })
            ->exists();

        if (!$areFriends) {
            return response()->json(['error' => 'You can only invite friends'], 422);
        }

        // Check for existing invite
        $existing = GameInvite::where('game_id', $game->id)
            ->where('receiver_id', $receiverId)
            ->first();

        if ($existing) {
            return response()->json(['error' => 'Already invited'], 422);
        }

        // Check slot availability
        $currentPlayers = GamePlayer::where('game_id', $game->id)->count();
        $pendingInvites = GameInvite::where('game_id', $game->id)->where('status', 'pending')->count();
        if ($currentPlayers + $pendingInvites >= $game->num_players) {
            return response()->json(['error' => 'All slots are filled or pending'], 422);
        }

        $invite = GameInvite::create([
            'game_id' => $game->id,
            'sender_id' => $request->user()->id,
            'receiver_id' => $receiverId,
        ]);

        broadcast(new GameInviteReceived(
            $receiverId,
            $invite->id,
            $game->id,
            $request->user()->name,
        ));

        // Send push notification (non-blocking — invite still works without it)
        try {
            $receiver = User::find($receiverId);
            if ($receiver) {
                app(OneSignalService::class)->sendToUser(
                    $receiver,
                    'Game Invite',
                    $request->user()->name . ' invited you to a game!',
                    ['type' => 'game_invite', 'game_id' => $game->id]
                );
            }
        } catch (\Throwable $e) {
            // Notification failure should never prevent invite from succeeding
        }

        return response()->json($invite->load(['sender', 'receiver']), 201);
    }

    public function acceptInvite(GameInvite $invite, Request $request): JsonResponse
    {
        if ($invite->receiver_id !== $request->user()->id) {
            return response()->json(['error' => 'Not your invite'], 403);
        }

        if ($invite->status !== 'pending') {
            return response()->json(['error' => 'Invite is no longer pending'], 422);
        }

        $game = $invite->game;

        // Determine next player number
        $nextPlayerNumber = GamePlayer::where('game_id', $game->id)->max('player_number') + 1;

        // Create GamePlayer record with user_id
        $player = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $request->user()->id,
            'player_number' => $nextPlayerNumber,
        ]);

        $invite->update(['status' => 'accepted']);

        broadcast(new PlayerJoinedGame(
            $game->id,
            $nextPlayerNumber,
            $request->user()->name,
        ));

        return response()->json([
            'player' => $player,
            'game_id' => $game->id,
        ]);
    }

    public function declineInvite(GameInvite $invite, Request $request): JsonResponse
    {
        if ($invite->receiver_id !== $request->user()->id) {
            return response()->json(['error' => 'Not your invite'], 403);
        }

        $invite->update(['status' => 'declined']);

        return response()->json(['declined' => true]);
    }

    public function selectCharacter(Game $game, Request $request): JsonResponse
    {
        if (!$game->isOnline() || $game->status !== 'setup') {
            return response()->json(['error' => 'Not in setup phase'], 422);
        }

        $validated = $request->validate([
            'character_id' => 'required|integer|exists:characters,id',
        ]);

        // Find the player record for this user
        $player = GamePlayer::where('game_id', $game->id)
            ->where('user_id', $request->user()->id)
            ->first();

        // Host might not have a GamePlayer yet — create one
        if (!$player && $game->user_id === $request->user()->id) {
            $nextPlayerNumber = GamePlayer::where('game_id', $game->id)->max('player_number');
            $nextPlayerNumber = $nextPlayerNumber ? $nextPlayerNumber + 1 : 1;
            $player = GamePlayer::create([
                'game_id' => $game->id,
                'user_id' => $request->user()->id,
                'player_number' => $nextPlayerNumber,
            ]);
        }

        if (!$player) {
            return response()->json(['error' => 'You are not in this game'], 403);
        }

        // Check character not already taken (cooperative only — duel allows same character)
        if ($game->game_type !== 'duel') {
            $taken = GamePlayer::where('game_id', $game->id)
                ->where('character_id', $validated['character_id'])
                ->where('id', '!=', $player->id)
                ->exists();

            if ($taken) {
                return response()->json(['error' => 'Character already taken'], 422);
            }
        }

        // Check if character is locked (dynamically via unlockables table)
        $unlockable = Unlockable::where('type', 'character')
            ->where('entity_id', $validated['character_id'])
            ->first();
        if ($unlockable) {
            $hasUnlocked = UserUnlockable::where('user_id', $request->user()->id)
                ->where('unlockable_id', $unlockable->id)
                ->exists();
            if (!$hasUnlocked) {
                return response()->json(['error' => 'This character is locked. Reach the required level or achievement to unlock it.'], 422);
            }
        }

        $player->update(['character_id' => $validated['character_id']]);

        // Auto-select character for bot player if present
        $botPlayer = GamePlayer::where('game_id', $game->id)
            ->where('is_bot', true)
            ->whereNull('character_id')
            ->first();

        if ($botPlayer) {
            $takenIds = GamePlayer::where('game_id', $game->id)
                ->whereNotNull('character_id')
                ->pluck('character_id')
                ->toArray();

            $botCharacter = Character::whereNotIn('id', $takenIds)->inRandomOrder()->first();
            if ($botCharacter) {
                $botPlayer->update(['character_id' => $botCharacter->id]);
            }
        }

        // Check if all players have selected
        $totalPlayers = GamePlayer::where('game_id', $game->id)->count();
        $selectedCount = GamePlayer::where('game_id', $game->id)->whereNotNull('character_id')->count();
        $allSelected = $totalPlayers >= $game->num_players && $selectedCount >= $game->num_players;

        broadcast(new PlayerSelectedCharacter(
            $game->id,
            $player->player_number,
            $validated['character_id'],
            $allSelected,
        ));

        return response()->json([
            'player' => $player->load('character'),
            'all_selected' => $allSelected,
        ]);
    }

    public function lobbyStatus(Game $game, Request $request): JsonResponse
    {
        $players = GamePlayer::where('game_id', $game->id)
            ->with(['character', 'user'])
            ->orderBy('player_number')
            ->get();

        $invites = GameInvite::where('game_id', $game->id)
            ->with(['sender', 'receiver'])
            ->get();

        $characters = Character::all();

        // Add lock info for each character (dynamically via unlockables table)
        $userId = $request->user()?->id;
        $charUnlockables = Unlockable::where('type', 'character')->get()->keyBy('entity_id');
        $userUnlockableIds = $userId
            ? UserUnlockable::where('user_id', $userId)->pluck('unlockable_id')->toArray()
            : [];

        // Only show characters the user owns (has in user_characters)
        $ownedCharacterIds = $userId
            ? \App\Models\UserCharacter::where('user_id', $userId)->pluck('character_id')->toArray()
            : [];

        $characters = $characters->map(function ($c) use ($charUnlockables, $userUnlockableIds, $ownedCharacterIds) {
            $charData = $c->toArray();
            $unlockable = $charUnlockables[$c->id] ?? null;
            $charData['is_locked_for_user'] = false;
            $charData['unlock_requirement'] = null;

            if ($unlockable) {
                $isUnlocked = in_array($unlockable->id, $userUnlockableIds);
                $charData['is_locked_for_user'] = !$isUnlocked;
                if (!$isUnlocked) {
                    $charData['unlock_requirement'] = $unlockable->unlock_method === 'level'
                        ? "Reach level {$unlockable->unlock_value}"
                        : "Earn required achievement";
                }
            }

            // Lock characters the user doesn't own
            if (!empty($ownedCharacterIds) && !in_array($c->id, $ownedCharacterIds)) {
                $charData['is_locked_for_user'] = true;
                $charData['unlock_requirement'] = 'You must own this advisor';
            }

            return $charData;
        });

        // Determine whose turn it is to pick (first player without a character)
        $pickingPlayer = $players->first(fn ($p) => !$p->character_id);
        $allJoined = $players->count() >= $game->num_players;
        $allSelected = $allJoined && $players->every(fn ($p) => $p->character_id);

        return response()->json([
            'players' => $players,
            'invites' => $invites,
            'characters' => $characters,
            'num_players' => $game->num_players,
            'host_id' => $game->user_id,
            'all_joined' => $allJoined,
            'all_selected' => $allSelected,
            'picking_player_number' => $pickingPlayer?->player_number,
        ]);
    }

    public function myPendingInvites(Request $request): JsonResponse
    {
        $invites = GameInvite::where('receiver_id', $request->user()->id)
            ->where('status', 'pending')
            ->with(['game', 'sender'])
            ->get();

        return response()->json($invites);
    }

    public function publicLobbies(): JsonResponse
    {
        $lobbies = Game::where('game_mode', 'online')
            ->where('status', 'setup')
            ->whereNull('tournament_match_id')
            ->with('user:id,name')
            ->get()
            ->filter(function ($game) {
                $currentPlayers = GamePlayer::where('game_id', $game->id)->count();
                return $currentPlayers < $game->num_players;
            })
            ->map(function ($game) {
                $currentPlayers = GamePlayer::where('game_id', $game->id)->count();
                return [
                    'id' => $game->id,
                    'host_name' => $game->user?->name ?? 'Unknown',
                    'game_type' => $game->game_type ?? 'cooperative',
                    'num_players' => $game->num_players,
                    'current_players' => $currentPlayers,
                    'is_private' => (bool) $game->is_private,
                    'is_custom' => (bool) $game->is_custom,
                ];
            })
            ->values();

        return response()->json($lobbies);
    }

    public function joinLobby(Game $game, Request $request): JsonResponse
    {
        if (!$game->isOnline() || $game->status !== 'setup') {
            return response()->json(['error' => 'Game is not accepting players.'], 422);
        }

        $currentPlayers = GamePlayer::where('game_id', $game->id)->count();
        if ($currentPlayers >= $game->num_players) {
            return response()->json(['error' => 'Game is full.'], 422);
        }

        // Check if already in this game
        $alreadyIn = GamePlayer::where('game_id', $game->id)
            ->where('user_id', $request->user()->id)
            ->exists();
        if ($alreadyIn) {
            return response()->json(['error' => 'Already in this game.'], 422);
        }

        // Password check for private games
        if ($game->is_private && $game->lobby_password) {
            $request->validate(['password' => 'required|string']);
            if (!Hash::check($request->password, $game->lobby_password)) {
                return response()->json(['error' => 'Incorrect password.'], 403);
            }
        }

        $nextPlayerNumber = GamePlayer::where('game_id', $game->id)->max('player_number') + 1;

        $player = GamePlayer::create([
            'game_id' => $game->id,
            'user_id' => $request->user()->id,
            'player_number' => $nextPlayerNumber,
        ]);

        broadcast(new PlayerJoinedGame(
            $game->id,
            $nextPlayerNumber,
            $request->user()->name,
        ));

        return response()->json([
            'player' => $player,
            'game_id' => $game->id,
        ]);
    }
}
