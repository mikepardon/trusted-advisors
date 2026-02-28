<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Character;
use App\Models\Event;
use App\Models\Game;
use App\Models\Item;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $totalUsers = User::count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();

        $totalGames = Game::count();
        $completedGames = Game::where('status', 'completed')->count();
        $activeGames = Game::where('status', 'active')->count();
        $setupGames = Game::where('status', 'setup')->count();
        $wins = Game::where('status', 'completed')->where('win', true)->count();
        $losses = Game::where('status', 'completed')->where('win', false)->count();

        $gamesByMode = [
            'single' => Game::where('game_mode', 'single')->count(),
            'pass_and_play' => Game::where('game_mode', 'pass_and_play')->count(),
            'online' => Game::where('game_mode', 'online')->count(),
        ];

        $gamesByType = [
            'cooperative' => Game::where(function ($q) {
                $q->where('game_type', 'cooperative')->orWhereNull('game_type');
            })->count(),
            'duel' => Game::where('game_type', 'duel')->count(),
        ];

        return response()->json([
            'total_users' => $totalUsers,
            'verified_users' => $verifiedUsers,
            'total_games' => $totalGames,
            'completed_games' => $completedGames,
            'active_games' => $activeGames,
            'setup_games' => $setupGames,
            'wins' => $wins,
            'losses' => $losses,
            'games_by_mode' => $gamesByMode,
            'games_by_type' => $gamesByType,
            'content_counts' => [
                'characters' => Character::count(),
                'cards' => Card::count(),
                'events' => Event::count(),
                'items' => Item::count(),
            ],
        ]);
    }
}
