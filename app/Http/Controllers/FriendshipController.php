<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendshipController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $friends = Friendship::where('status', 'accepted')
            ->where(fn ($q) => $q->where('sender_id', $userId)->orWhere('receiver_id', $userId))
            ->with(['sender:id,name', 'receiver:id,name'])
            ->get()
            ->map(function ($f) use ($userId) {
                $friend = $f->sender_id === $userId ? $f->receiver : $f->sender;
                return ['id' => $f->id, 'user' => $friend];
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

        $receiver = User::where('name', $validated['username'])->first();

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
