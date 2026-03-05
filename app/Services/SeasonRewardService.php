<?php

namespace App\Services;

use App\Events\UserNotificationReceived;
use App\Models\Season;
use App\Models\SeasonReward;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;

class SeasonRewardService
{
    public function __construct(private OneSignalService $oneSignal)
    {
    }

    public function processSeasonEnd(Season $season): array
    {
        $rewards = $season->rewards()->orderBy('placement')->get();
        $distributed = 0;

        // Group rewards by metric
        $rewardsByMetric = $rewards->groupBy('metric');

        foreach ($rewardsByMetric as $metric => $metricRewards) {
            $leaderboard = $this->buildLeaderboard($season, $metric);

            foreach ($metricRewards as $reward) {
                $placement = $reward->placement;
                $entry = $leaderboard->get($placement - 1); // 0-indexed

                if (!$entry) continue;

                $user = User::find($entry->user_id);
                if (!$user || $user->is_bot) continue;

                $metricLabel = match ($metric) {
                    'elo' => 'ELO Rating',
                    'score' => 'Highest Score',
                    'wins' => 'Most Wins',
                    default => $metric,
                };

                $notification = UserNotification::create([
                    'user_id' => $user->id,
                    'type' => 'season_reward',
                    'title' => "Season Reward: {$season->name}",
                    'message' => "You placed {$this->ordinal($placement)} in {$metricLabel}! Claim your reward.",
                    'data' => [
                        'season_id' => $season->id,
                        'season_name' => $season->name,
                        'rank' => $placement,
                        'metric' => $metric,
                        'reward_xp' => $reward->reward_xp ?? 0,
                        'reward_coins' => $reward->reward_coins ?? 0,
                        'reward_character_id' => $reward->reward_character_id,
                        'reward_dice_theme_id' => $reward->reward_dice_theme_id,
                        'reward_title' => $reward->reward_title,
                    ],
                ]);

                try {
                    broadcast(new UserNotificationReceived(
                        $user->id,
                        $notification->id,
                        'season_reward',
                        $notification->title,
                    ));
                } catch (\Throwable) {}

                $this->oneSignal->sendToUser(
                    $user,
                    "Season \"{$season->name}\" has ended!",
                    "You placed {$this->ordinal($placement)} in {$metricLabel}. Check your rewards!",
                    ['type' => 'season_reward', 'season_id' => $season->id],
                );

                $distributed++;
            }
        }

        // Mark season inactive
        $season->update(['is_active' => false]);

        return ['rewards_distributed' => $distributed];
    }

    private function buildLeaderboard(Season $season, string $metric)
    {
        $query = DB::table('games')
            ->join('game_players', 'games.id', '=', 'game_players.game_id')
            ->where('games.season_id', $season->id)
            ->where('games.status', 'completed')
            ->whereNotNull('game_players.user_id');

        if ($metric === 'wins') {
            $query->select(
                'game_players.user_id',
                DB::raw("COUNT(CASE WHEN games.win = TRUE OR game_players.player_number = games.winner_player_number THEN 1 END) as value"),
            )
            ->groupBy('game_players.user_id')
            ->orderByDesc('value');
        } elseif ($metric === 'elo') {
            // Use current ELO rating for users who participated
            $query->select('game_players.user_id')
                ->distinct()
                ->groupBy('game_players.user_id');

            $userIds = $query->pluck('user_id');

            return User::whereIn('id', $userIds)
                ->orderByDesc('elo_rating')
                ->get()
                ->map(fn ($u) => (object) ['user_id' => $u->id, 'value' => $u->elo_rating])
                ->values();
        } elseif ($metric === 'score') {
            // Best single-game score
            $query->select(
                'game_players.user_id',
                DB::raw('MAX(games.final_score) as value'),
            )
            ->groupBy('game_players.user_id')
            ->orderByDesc('value');
        }

        return $query->get()->values();
    }

    private function ordinal(int $n): string
    {
        $s = ['th', 'st', 'nd', 'rd'];
        $v = $n % 100;
        return $n . ($s[($v - 20) % 10] ?? $s[$v] ?? $s[0]);
    }
}
