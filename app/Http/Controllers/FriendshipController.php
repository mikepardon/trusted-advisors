<?php

namespace App\Http\Controllers;

use App\Events\FriendRequestReceived;
use App\Models\Friendship;
use App\Models\User;
use App\Services\OneSignalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $friends = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->with(['sender:id,name,level,elo_rating,last_login_at', 'receiver:id,name,level,elo_rating,last_login_at'])
            ->get()
            ->map(function ($f) use ($userId) {
                $friend = $f->sender_id === $userId ? $f->receiver : $f->sender;

                // Count wins/losses/draws for this friend
                $friendGames = \App\Models\GamePlayer::where('user_id', $friend->id)
                    ->whereHas('game', fn ($q) => $q->where('status', 'completed'))
                    ->with('game:id,winner_player_number,updated_at')
                    ->get();

                $wins = 0;
                $losses = 0;
                $draws = 0;
                $lastPlayed = null;

                foreach ($friendGames as $gp) {
                    if ($gp->game->updated_at && (!$lastPlayed || $gp->game->updated_at->gt($lastPlayed))) {
                        $lastPlayed = $gp->game->updated_at;
                    }
                    if ($gp->game->winner_player_number === null) {
                        $draws++;
                    } elseif ($gp->game->winner_player_number === $gp->player_number) {
                        $wins++;
                    } else {
                        $losses++;
                    }
                }

                return [
                    'id' => $f->id,
                    'user' => $friend,
                    'last_login_at' => $friend->last_login_at?->toIso8601String(),
                    'stats' => [
                        'wins' => $wins,
                        'losses' => $losses,
                        'draws' => $draws,
                        'last_played' => $lastPlayed?->diffForHumans(),
                    ],
                ];
            });

        $pendingSent = Friendship::where('status', 'pending')
            ->where('sender_id', $userId)
            ->with('receiver:id,name')
            ->get()
            ->map(fn ($f) => ['id' => $f->id, 'user' => $f->receiver]);

        $pendingReceived = Friendship::where('status', 'pending')
            ->where('receiver_id', $userId)
            ->with('sender:id,name')
            ->get()
            ->map(fn ($f) => ['id' => $f->id, 'user' => $f->sender]);

        return response()->json([
            'friends' => $friends->values(),
            'pending_sent' => $pendingSent->values(),
            'pending_received' => $pendingReceived->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'username' => 'required|string',
        ]);

        $receiver = User::whereRaw('LOWER(name) = ?', [strtolower($validated['username'])])->first();

        if (!$receiver) {
            return response()->json(['message' => 'User not found'], 404);
        }

        if ($receiver->id === $request->user()->id) {
            return response()->json(['message' => 'Cannot send a friend request to yourself'], 422);
        }

        $existing = Friendship::where(function ($q) use ($request, $receiver) {
            $q->where('sender_id', $request->user()->id)->where('receiver_id', $receiver->id);
        })->orWhere(function ($q) use ($request, $receiver) {
            $q->where('sender_id', $receiver->id)->where('receiver_id', $request->user()->id);
        })->first();

        if ($existing) {
            return response()->json(['message' => 'Friendship already exists'], 422);
        }

        $friendship = Friendship::create([
            'sender_id' => $request->user()->id,
            'receiver_id' => $receiver->id,
        ]);

        broadcast(new FriendRequestReceived($receiver->id, $request->user()->name));

        // Send push notification to the receiver (non-blocking — friendship still works without it)
        try {
            app(OneSignalService::class)->sendToUser(
                $receiver,
                'Friend Request',
                $request->user()->name . ' sent you a friend request!',
                ['type' => 'friend_request']
            );
        } catch (\Throwable $e) {
            // Notification failure should never prevent friend request from succeeding
        }

        return response()->json($friendship->load('receiver:id,name'), 201);
    }

    public function accept(Request $request, Friendship $friendship): JsonResponse
    {
        if ($friendship->receiver_id !== $request->user()->id) {
            return response()->json(['message' => 'Only the recipient can accept'], 403);
        }

        if ($friendship->status !== 'pending') {
            return response()->json(['message' => 'Request is not pending'], 422);
        }

        $friendship->update(['status' => 'accepted']);

        // Notify sender that their request was accepted
        try {
            $sender = User::find($friendship->sender_id);
            if ($sender) {
                app(OneSignalService::class)->notifyUser(
                    $sender,
                    'social',
                    'Friend Request Accepted',
                    $request->user()->name . ' accepted your friend request!',
                    ['type' => 'friend_accepted'],
                );
            }
        } catch (\Throwable) {
            // Notification failure should never prevent accept from succeeding
        }

        return response()->json($friendship);
    }

    public function destroy(Request $request, Friendship $friendship): JsonResponse
    {
        $userId = $request->user()->id;

        if ($friendship->sender_id !== $userId && $friendship->receiver_id !== $userId) {
            return response()->json(['message' => 'Not authorized'], 403);
        }

        $friendship->delete();

        return response()->json(['message' => 'Removed']);
    }
}
