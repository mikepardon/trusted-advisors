<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Character;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class BalanceDashboardController extends Controller
{
    public function cardStats(Request $request): JsonResponse
    {
        $cacheKey = 'balance.cards.' . md5(json_encode($request->only('game_mode', 'game_type', 'season_id', 'date_from', 'date_to')));

        $data = Cache::remember($cacheKey, 900, function () use ($request) {
            $query = DB::table('game_round_results')
                ->join('games', 'games.id', '=', 'game_round_results.game_id')
                ->join('cards', 'cards.id', '=', 'game_round_results.card_id')
                ->where('games.status', 'completed')
                ->select(
                    'cards.id',
                    'cards.title',
                    'cards.difficulty',
                    'cards.category',
                    DB::raw('COUNT(*) as appearances'),
                    DB::raw('SUM(CASE WHEN game_round_results.success = 1 THEN 1 ELSE 0 END) as success_count'),
                    DB::raw('ROUND(AVG(CASE WHEN game_round_results.success = 1 THEN 1.0 ELSE 0.0 END) * 100, 1) as success_rate'),
                )
                ->groupBy('cards.id', 'cards.title', 'cards.difficulty', 'cards.category');

            $this->applyGameFilters($query, $request);

            return $query->orderByDesc('appearances')->get();
        });

        return response()->json($data);
    }

    public function characterStats(Request $request): JsonResponse
    {
        $cacheKey = 'balance.characters.' . md5(json_encode($request->only('game_mode', 'game_type', 'season_id', 'date_from', 'date_to')));

        $data = Cache::remember($cacheKey, 900, function () use ($request) {
            $query = DB::table('game_players')
                ->join('games', 'games.id', '=', 'game_players.game_id')
                ->join('characters', 'characters.id', '=', 'game_players.character_id')
                ->where('games.status', 'completed')
                ->select(
                    'characters.id',
                    'characters.name',
                    DB::raw('COUNT(*) as pick_count'),
                    DB::raw('SUM(CASE
                        WHEN games.game_type = \'duel\' AND game_players.player_number = games.winner_player_number THEN 1
                        WHEN games.game_type != \'duel\' AND games.win = 1 THEN 1
                        ELSE 0
                    END) as win_count'),
                    DB::raw('ROUND(AVG(CASE
                        WHEN games.game_type = \'duel\' AND game_players.player_number = games.winner_player_number THEN 1.0
                        WHEN games.game_type != \'duel\' AND games.win = 1 THEN 1.0
                        ELSE 0.0
                    END) * 100, 1) as win_rate'),
                )
                ->groupBy('characters.id', 'characters.name');

            $this->applyGameFilters($query, $request);

            return $query->orderByDesc('pick_count')->get();
        });

        return response()->json($data);
    }

    public function statDistribution(Request $request): JsonResponse
    {
        $cacheKey = 'balance.stats.' . md5(json_encode($request->only('game_mode', 'game_type', 'season_id', 'date_from', 'date_to')));

        $data = Cache::remember($cacheKey, 900, function () use ($request) {
            // For coop games, stats are on the games table
            $coopQuery = DB::table('games')
                ->where('status', 'completed')
                ->where('game_type', '!=', 'duel')
                ->select(
                    DB::raw("'cooperative' as source"),
                    DB::raw('ROUND(AVG(strength), 1) as avg_strength'),
                    DB::raw('ROUND(AVG(wisdom), 1) as avg_wisdom'),
                    DB::raw('ROUND(AVG(morale), 1) as avg_morale'),
                    DB::raw('ROUND(AVG(defence), 1) as avg_defence'),
                    DB::raw('COUNT(*) as game_count'),
                );

            $this->applyGameFilters($coopQuery, $request, false);

            // For duel games, stats come from game_player_kingdoms
            $duelQuery = DB::table('game_player_kingdoms')
                ->join('games', 'games.id', '=', 'game_player_kingdoms.game_id')
                ->where('games.status', 'completed')
                ->where('games.game_type', 'duel')
                ->select(
                    DB::raw("'duel' as source"),
                    DB::raw('ROUND(AVG(game_player_kingdoms.strength), 1) as avg_strength'),
                    DB::raw('ROUND(AVG(game_player_kingdoms.wisdom), 1) as avg_wisdom'),
                    DB::raw('ROUND(AVG(game_player_kingdoms.morale), 1) as avg_morale'),
                    DB::raw('ROUND(AVG(game_player_kingdoms.defence), 1) as avg_defence'),
                    DB::raw('COUNT(DISTINCT games.id) as game_count'),
                );

            $this->applyGameFilters($duelQuery, $request, false);

            return [
                'cooperative' => $coopQuery->first(),
                'duel' => $duelQuery->first(),
            ];
        });

        return response()->json($data);
    }

    private function applyGameFilters($query, Request $request, bool $joinedAlready = true): void
    {
        $gamesTable = $joinedAlready ? 'games' : null;

        if ($request->filled('game_mode')) {
            $col = $gamesTable ? "{$gamesTable}.game_mode" : 'game_mode';
            $query->where($col, $request->game_mode);
        }

        if ($request->filled('game_type')) {
            $col = $gamesTable ? "{$gamesTable}.game_type" : 'game_type';
            $query->where($col, $request->game_type);
        }

        if ($request->filled('season_id')) {
            $col = $gamesTable ? "{$gamesTable}.season_id" : 'season_id';
            $query->where($col, $request->season_id);
        }

        if ($request->filled('date_from')) {
            $col = $gamesTable ? "{$gamesTable}.created_at" : 'created_at';
            $query->where($col, '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $col = $gamesTable ? "{$gamesTable}.created_at" : 'created_at';
            $query->where($col, '<=', $request->date_to . ' 23:59:59');
        }
    }
}
