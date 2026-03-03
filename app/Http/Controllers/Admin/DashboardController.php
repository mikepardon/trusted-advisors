<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Character;
use App\Models\Event;
use App\Models\Game;
use App\Models\Item;
use App\Models\Unlockable;
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
        $cancelledGames = Game::where('status', 'cancelled')->count();
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
            'cancelled_games' => $cancelledGames,
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

    public function levels(): JsonResponse
    {
        $maxLevel = 50;
        $unlockables = Unlockable::where('unlock_method', 'level')
            ->get()
            ->groupBy('unlock_value');

        // Load entity names separately since the dynamic entity() relationship
        // doesn't work with eager loading (type is null on the blank model)
        $characterIds = $unlockables->flatten()->where('type', 'character')->pluck('entity_id')->unique();
        $itemIds = $unlockables->flatten()->where('type', 'item')->pluck('entity_id')->unique();
        $characterNames = Character::whereIn('id', $characterIds)->pluck('name', 'id');
        $itemNames = Item::whereIn('id', $itemIds)->pluck('name', 'id');

        $levels = [];
        for ($i = 1; $i <= $maxLevel; $i++) {
            $xpNeeded = User::xpForLevel($i);
            $xpToNext = User::xpForLevel($i + 1) - $xpNeeded;
            $rewards = [];

            if (isset($unlockables[$i])) {
                foreach ($unlockables[$i] as $u) {
                    $entityName = $u->type === 'character'
                        ? ($characterNames[$u->entity_id] ?? "#{$u->entity_id}")
                        : ($itemNames[$u->entity_id] ?? "#{$u->entity_id}");
                    $rewards[] = [
                        'id' => $u->id,
                        'type' => $u->type,
                        'entity_id' => $u->entity_id,
                        'entity_name' => $entityName,
                    ];
                }
            }

            $levels[] = [
                'level' => $i,
                'total_xp' => $xpNeeded,
                'xp_to_next' => $xpToNext,
                'rewards' => $rewards,
            ];
        }

        return response()->json($levels);
    }
}
