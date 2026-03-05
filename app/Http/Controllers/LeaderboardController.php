<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LeaderboardController extends Controller
{
    public function global(Request $request): JsonResponse
    {
        $metric = $request->input('metric', 'wins');
        $seasonId = $request->input('season_id');
        $playerCount = $request->input('player_count');
        $gameType = $request->input('game_type');

        return $this->buildLeaderboard($metric, $seasonId, $playerCount, $gameType, null, $request->user());
    }

    public function friends(Request $request): JsonResponse
    {
        $metric = $request->input('metric', 'wins');
        $seasonId = $request->input('season_id');
        $playerCount = $request->input('player_count');
        $gameType = $request->input('game_type');

        // Get friend IDs
        $userId = $request->user()->id;
        $friendIds = Friendship::where('status', 'accepted')
            ->where(function ($q) use ($userId) {
                $q->where('sender_id', $userId)->orWhere('receiver_id', $userId);
            })
            ->get()
            ->map(function ($f) use ($userId) {
                return $f->sender_id === $userId ? $f->receiver_id : $f->sender_id;
            })
            ->push($userId)
            ->unique()
            ->values()
            ->toArray();

        return $this->buildLeaderboard($metric, $seasonId, $playerCount, $gameType, $friendIds, $request->user());
    }

    private function buildLeaderboard(
        string $metric,
        ?int $seasonId,
        ?int $playerCount,
        ?string $gameType,
        ?array $userIds,
        User $currentUser
    ): JsonResponse {
        if ($metric === 'elo') {
            return $this->eloLeaderboard($userIds, $currentUser);
        }

        if ($metric === 'xp') {
            return $this->xpLeaderboard($userIds, $currentUser);
        }

        // Wins or score: need to query games (exclude custom games)
        $query = Game::where('status', 'completed')
            ->where(function ($q) {
                $q->where('is_custom', false)->orWhereNull('is_custom');
            });

        if ($seasonId) {
            $query->where('season_id', $seasonId);
        }
        if ($playerCount) {
            $query->where('num_players', $playerCount);
        }
        if ($gameType) {
            $query->where('game_type', $gameType);
        }

        $games = $query->get();

        // Build per-user stats
        $userStats = [];

        foreach ($games as $game) {
            $participants = GamePlayer::where('game_id', $game->id)->get();

            foreach ($participants as $participant) {
                if (!$participant->user_id) continue;
                if ($userIds && !in_array($participant->user_id, $userIds)) continue;

                if (!isset($userStats[$participant->user_id])) {
                    $userStats[$participant->user_id] = ['wins' => 0, 'score' => 0];
                }

                // Score — track highest single-game score
                $gameScore = 0;
                if ($game->isDuel()) {
                    $kingdom = $game->playerKingdoms()->where('game_player_id', $participant->id)->first();
                    if ($kingdom) {
                        $gameScore = $kingdom->wealth + $kingdom->influence +
                            $kingdom->security + $kingdom->religion + $kingdom->food + $kingdom->happiness;
                    }
                } else {
                    $gameScore = $game->final_score
                        ?? ($game->wealth + $game->influence + $game->security + $game->religion + $game->food + $game->happiness);
                }
                $userStats[$participant->user_id]['score'] = max($userStats[$participant->user_id]['score'], $gameScore);

                // Wins
                if ($game->isDuel()) {
                    if ($participant->player_number === $game->winner_player_number) {
                        $userStats[$participant->user_id]['wins']++;
                    }
                } else {
                    if ($game->win) {
                        $userStats[$participant->user_id]['wins']++;
                    }
                }
            }
        }

        // Sort
        $sortKey = $metric === 'score' ? 'score' : 'wins';
        arsort($userStats);
        uasort($userStats, fn($a, $b) => $b[$sortKey] <=> $a[$sortKey]);

        // Build result
        $userIdsOrdered = array_keys(array_slice($userStats, 0, 50, true));
        $users = User::whereIn('id', $userIdsOrdered)->get()->keyBy('id');

        $result = [];
        $rank = 1;
        foreach ($userIdsOrdered as $userId) {
            $user = $users[$userId] ?? null;
            if (!$user) continue;

            $result[] = [
                'rank' => $rank++,
                'user_id' => $user->id,
                'username' => $user->name,
                'value' => $userStats[$userId][$sortKey],
                'level' => $user->level,
                'is_current_user' => $user->id === $currentUser->id,
            ];
        }

        return response()->json($result);
    }

    private function eloLeaderboard(?array $userIds, User $currentUser): JsonResponse
    {
        $query = User::orderByDesc('elo_rating');

        if ($userIds) {
            $query->whereIn('id', $userIds);
        }

        $users = $query->limit(50)->get();

        $result = [];
        $rank = 1;
        foreach ($users as $user) {
            $result[] = [
                'rank' => $rank++,
                'user_id' => $user->id,
                'username' => $user->name,
                'value' => $user->elo_rating,
                'level' => $user->level,
                'is_current_user' => $user->id === $currentUser->id,
            ];
        }

        return response()->json($result);
    }

    private function xpLeaderboard(?array $userIds, User $currentUser): JsonResponse
    {
        $query = User::orderByDesc('xp');

        if ($userIds) {
            $query->whereIn('id', $userIds);
        }

        $users = $query->limit(50)->get();

        $result = [];
        $rank = 1;
        foreach ($users as $user) {
            $result[] = [
                'rank' => $rank++,
                'user_id' => $user->id,
                'username' => $user->name,
                'value' => $user->xp,
                'level' => $user->level,
                'is_current_user' => $user->id === $currentUser->id,
            ];
        }

        return response()->json($result);
    }
}
