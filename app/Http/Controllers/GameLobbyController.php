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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        // Check character not already taken
        $taken = GamePlayer::where('game_id', $game->id)
            ->where('character_id', $validated['character_id'])
            ->where('id', '!=', $player->id)
            ->exists();

        if ($taken) {
            return response()->json(['error' => 'Character already taken'], 422);
        }

        $player->update(['character_id' => $validated['character_id']]);

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

    public function lobbyStatus(Game $game): JsonResponse
    {
        $players = GamePlayer::where('game_id', $game->id)
            ->with(['character', 'user'])
            ->orderBy('player_number')
            ->get();

        $invites = GameInvite::where('game_id', $game->id)
            ->with(['sender', 'receiver'])
            ->get();

        $characters = Character::all();

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
}
