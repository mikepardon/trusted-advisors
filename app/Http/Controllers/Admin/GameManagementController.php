<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Game;
use Illuminate\Http\JsonResponse;

class GameManagementController extends Controller
{
    public function index(): JsonResponse
    {
        $games = Game::with(['user:id,name', 'players:id,game_id,player_number,user_id', 'players.user:id,name'])
            ->whereIn('status', ['setup', 'active'])
            ->orderByDesc('updated_at')
            ->get()
            ->map(function ($game) {
                return [
                    'id' => $game->id,
                    'status' => $game->status,
                    'game_mode' => $game->game_mode,
                    'game_type' => $game->game_type ?? 'cooperative',
                    'num_players' => $game->num_players,
                    'current_round' => $game->current_round,
                    'total_rounds' => $game->total_rounds,
                    'creator' => $game->user?->name ?? 'Guest',
                    'players' => $game->players->map(fn ($p) => $p->user?->name ?? 'Player ' . $p->player_number)->values(),
                    'created_at' => $game->created_at->toDateTimeString(),
                    'updated_at' => $game->updated_at->toDateTimeString(),
                ];
            });

        return response()->json($games);
    }

    public function cancel(Game $game): JsonResponse
    {
        if (in_array($game->status, ['completed', 'cancelled'])) {
            return response()->json(['error' => 'Game is already finished.'], 422);
        }

        $game->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json(['message' => 'Game cancelled.']);
    }
}
