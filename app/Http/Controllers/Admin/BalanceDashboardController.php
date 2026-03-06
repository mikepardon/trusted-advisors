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
                    DB::raw('SUM(CASE WHEN game_round_results.success = true THEN 1 ELSE 0 END) as success_count'),
                    DB::raw('ROUND(AVG(CASE WHEN game_round_results.success = true THEN 1.0 ELSE 0.0 END) * 100, 1) as success_rate'),
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
                        WHEN games.game_type != \'duel\' AND games.win = true THEN 1
                        ELSE 0
                    END) as win_count'),
                    DB::raw('ROUND(AVG(CASE
                        WHEN games.game_type = \'duel\' AND game_players.player_number = games.winner_player_number THEN 1.0
                        WHEN games.game_type != \'duel\' AND games.win = true THEN 1.0
                        ELSE 0.0
                    END) * 100, 1) as win_rate'),
                )
                ->groupBy('characters.id', 'characters.name');

            $this->applyGameFilters($query, $request);

            return $query->orderByDesc('pick_count')->get();
        });

        return response()->json($data);
    }

    public function itemStats(Request $request): JsonResponse
    {
        $cacheKey = 'balance.items.' . md5(json_encode($request->only('game_mode', 'game_type', 'season_id', 'date_from', 'date_to')));

        $data = Cache::remember($cacheKey, 900, function () use ($request) {
            $query = DB::table('game_player_items')
                ->join('game_players', 'game_players.id', '=', 'game_player_items.game_player_id')
                ->join('games', 'games.id', '=', 'game_players.game_id')
                ->join('items', 'items.id', '=', 'game_player_items.item_id')
                ->where('games.status', 'completed')
                ->select(
                    'items.id',
                    'items.name',
                    'items.effect_type',
                    'items.is_negative',
                    DB::raw('COUNT(*) as times_acquired'),
                    DB::raw('COUNT(DISTINCT games.id) as games_appeared_in'),
                    DB::raw('SUM(CASE WHEN game_player_items.is_used = true THEN 1 ELSE 0 END) as times_used'),
                    DB::raw('SUM(CASE WHEN game_player_items.is_cursed = true THEN 1 ELSE 0 END) as times_cursed'),
                )
                ->groupBy('items.id', 'items.name', 'items.effect_type', 'items.is_negative');

            $this->applyGameFilters($query, $request);

            return $query->orderByDesc('times_acquired')->get();
        });

        return response()->json($data);
    }

    public function eventStats(Request $request): JsonResponse
    {
        $cacheKey = 'balance.events.' . md5(json_encode($request->only('game_mode', 'game_type', 'season_id', 'date_from', 'date_to')));

        $data = Cache::remember($cacheKey, 900, function () use ($request) {
            // Count event appearances from the event_order JSON array on games
            $gamesQuery = DB::table('games')
                ->where('status', 'completed')
                ->whereNotNull('event_order');

            if ($request->filled('game_mode')) {
                $gamesQuery->where('game_mode', $request->game_mode);
            }
            if ($request->filled('game_type')) {
                $gamesQuery->where('game_type', $request->game_type);
            }
            if ($request->filled('season_id')) {
                $gamesQuery->where('season_id', $request->season_id);
            }
            if ($request->filled('date_from')) {
                $gamesQuery->where('created_at', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $gamesQuery->where('created_at', '<=', $request->date_to . ' 23:59:59');
            }

            $games = $gamesQuery->select('event_order')->get();

            $eventCounts = [];
            $totalGames = $games->count();

            foreach ($games as $game) {
                $eventOrder = json_decode($game->event_order, true);
                if (!is_array($eventOrder)) continue;

                $seen = [];
                foreach ($eventOrder as $eventId) {
                    $eventCounts[$eventId] = ($eventCounts[$eventId] ?? 0) + 1;
                    $seen[$eventId] = true;
                }
            }

            if (empty($eventCounts)) {
                return [];
            }

            $events = DB::table('events')
                ->whereIn('id', array_keys($eventCounts))
                ->get()
                ->keyBy('id');

            $results = [];
            foreach ($eventCounts as $eventId => $count) {
                $event = $events[$eventId] ?? null;
                if (!$event) continue;

                $results[] = [
                    'id' => $eventId,
                    'title' => $event->title,
                    'effect' => $event->effect,
                    'mechanic' => $event->mechanic,
                    'times_drawn' => $count,
                    'total_games' => $totalGames,
                ];
            }

            usort($results, fn ($a, $b) => $b['times_drawn'] - $a['times_drawn']);

            return $results;
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
