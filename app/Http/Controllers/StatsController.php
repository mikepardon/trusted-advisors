<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\UserAchievement;
use App\Models\Achievement;
use App\Models\UserEloHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StatsController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $participantGameIds = GamePlayer::where('user_id', $userId)->pluck('game_id');

        $games = Game::where('status', 'completed')
            ->where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->get();

        $totalGames = $games->count();

        // Count wins considering duel mode
        $wins = 0;
        $losses = 0;
        $bestScore = 0;
        $totalDuration = 0;
        $characterCounts = [];
        $currentStreak = 0;
        $maxStreak = 0;
        $streakBroken = false;

        // By type/mode
        $byType = ['cooperative' => ['games' => 0, 'wins' => 0], 'duel' => ['games' => 0, 'wins' => 0]];
        $byMode = ['single' => ['games' => 0, 'wins' => 0], 'pass_and_play' => ['games' => 0, 'wins' => 0], 'online' => ['games' => 0, 'wins' => 0]];

        $sortedGames = $games->sortByDesc('updated_at');

        foreach ($sortedGames as $game) {
            $isWin = false;
            $score = 0;

            if ($game->isDuel()) {
                $player = GamePlayer::where('game_id', $game->id)->where('user_id', $userId)->first();
                $isWin = $player && $player->player_number === $game->winner_player_number;
                $kingdom = $game->playerKingdoms()->where('game_player_id', $player?->id)->first();
                $score = $kingdom ? $kingdom->totalScore() : 0;
            } else {
                $isWin = (bool) $game->win;
                $score = $game->final_score ?? $game->baseScore();
            }

            if ($isWin) $wins++;
            else $losses++;

            if ($score > $bestScore) $bestScore = $score;

            // Streaks (from most recent)
            if (!$streakBroken) {
                if ($isWin) {
                    $currentStreak++;
                } else {
                    $streakBroken = true;
                }
            }

            // Duration estimate: rounds completed * ~30 seconds as approximation
            if ($game->created_at && $game->updated_at) {
                $totalDuration += $game->created_at->diffInSeconds($game->updated_at);
            }

            // Type/mode breakdown
            $type = $game->game_type ?? 'cooperative';
            $mode = $game->game_mode ?? 'single';
            if (isset($byType[$type])) {
                $byType[$type]['games']++;
                if ($isWin) $byType[$type]['wins']++;
            }
            if (isset($byMode[$mode])) {
                $byMode[$mode]['games']++;
                if ($isWin) $byMode[$mode]['wins']++;
            }
        }

        // Favorite character
        $favoriteChar = GamePlayer::where('user_id', $userId)
            ->whereNotNull('character_id')
            ->select('character_id', DB::raw('COUNT(*) as count'))
            ->groupBy('character_id')
            ->orderByDesc('count')
            ->first();

        $favoriteCharName = null;
        if ($favoriteChar) {
            $char = \App\Models\Character::find($favoriteChar->character_id);
            $favoriteCharName = $char?->name;
        }

        // Max win streak across all history
        $allStreak = 0;
        $tempStreak = 0;
        foreach ($sortedGames as $game) {
            $isWin = false;
            if ($game->isDuel()) {
                $player = GamePlayer::where('game_id', $game->id)->where('user_id', $userId)->first();
                $isWin = $player && $player->player_number === $game->winner_player_number;
            } else {
                $isWin = (bool) $game->win;
            }
            if ($isWin) {
                $tempStreak++;
                $allStreak = max($allStreak, $tempStreak);
            } else {
                $tempStreak = 0;
            }
        }

        $avgDuration = $totalGames > 0 ? (int) ($totalDuration / $totalGames) : 0;

        return response()->json([
            'total_games' => $totalGames,
            'total_wins' => $wins,
            'total_losses' => $losses,
            'win_rate' => $totalGames > 0 ? round($wins / $totalGames * 100, 1) : 0,
            'best_score' => $bestScore,
            'highest_elo' => UserEloHistory::where('user_id', $userId)->max('new_elo') ?? $user->elo_rating,
            'current_elo' => $user->elo_rating,
            'current_streak' => $currentStreak,
            'best_streak' => $allStreak,
            'avg_duration_seconds' => $avgDuration,
            'favorite_character' => $favoriteCharName,
            'by_type' => $byType,
            'by_mode' => $byMode,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        // ELO history (last 30 entries)
        $eloHistory = UserEloHistory::where('user_id', $userId)
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->reverse()
            ->values()
            ->map(fn ($h) => [
                'date' => $h->created_at->toDateString(),
                'elo' => $h->new_elo,
            ]);

        // Games per day (last 30 days)
        $thirtyDaysAgo = Carbon::now()->subDays(30)->startOfDay();
        $participantGameIds = GamePlayer::where('user_id', $userId)->pluck('game_id');

        $gamesPerDay = Game::where('status', 'completed')
            ->where('updated_at', '>=', $thirtyDaysAgo)
            ->where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->select(DB::raw('DATE(updated_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->pluck('count', 'date');

        // Fill missing days with 0
        $activity = [];
        for ($i = 29; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i)->format('Y-m-d');
            $activity[] = [
                'date' => $date,
                'count' => $gamesPerDay[$date] ?? 0,
            ];
        }

        // Win rate trend (rolling 10-game window)
        $recentGames = Game::where('status', 'completed')
            ->where(function ($q) use ($userId, $participantGameIds) {
                $q->where('user_id', $userId)->orWhereIn('id', $participantGameIds);
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $winRateTrend = [];
        $windowSize = 10;
        for ($i = 0; $i <= max(0, $recentGames->count() - $windowSize); $i++) {
            $window = $recentGames->slice($i, $windowSize);
            $windowWins = 0;
            foreach ($window as $game) {
                if ($game->isDuel()) {
                    $player = GamePlayer::where('game_id', $game->id)->where('user_id', $userId)->first();
                    if ($player && $player->player_number === $game->winner_player_number) $windowWins++;
                } else {
                    if ($game->win) $windowWins++;
                }
            }
            $winRateTrend[] = round($windowWins / $windowSize * 100, 1);
        }

        return response()->json([
            'elo_history' => $eloHistory,
            'activity' => $activity,
            'win_rate_trend' => array_reverse($winRateTrend),
        ]);
    }

    public function characters(Request $request): JsonResponse
    {
        $user = $request->user();
        $userId = $user->id;

        $participantGameIds = GamePlayer::where('user_id', $userId)->pluck('game_id');

        $characterStats = GamePlayer::where('user_id', $userId)
            ->whereNotNull('character_id')
            ->whereHas('game', fn ($q) => $q->where('status', 'completed'))
            ->select('character_id', DB::raw('COUNT(*) as games'))
            ->groupBy('character_id')
            ->get()
            ->map(function ($entry) use ($userId) {
                $character = \App\Models\Character::find($entry->character_id);

                // Count wins with this character
                $winCount = 0;
                $gameIds = GamePlayer::where('user_id', $userId)
                    ->where('character_id', $entry->character_id)
                    ->pluck('game_id');

                $games = Game::where('status', 'completed')->whereIn('id', $gameIds)->get();

                foreach ($games as $game) {
                    if ($game->isDuel()) {
                        $player = GamePlayer::where('game_id', $game->id)->where('user_id', $userId)->first();
                        if ($player && $player->player_number === $game->winner_player_number) $winCount++;
                    } else {
                        if ($game->win) $winCount++;
                    }
                }

                return [
                    'character_id' => $entry->character_id,
                    'name' => $character?->name ?? 'Unknown',
                    'image_url' => $character?->image_url,
                    'games' => $entry->games,
                    'wins' => $winCount,
                    'losses' => $entry->games - $winCount,
                    'win_rate' => $entry->games > 0 ? round($winCount / $entry->games * 100, 1) : 0,
                ];
            })
            ->sortByDesc('games')
            ->values();

        return response()->json($characterStats);
    }
}
