<?php

namespace App\Services;

use App\Models\Achievement;
use App\Models\DailyChallenge;
use App\Models\DailyChallengeEntry;
use App\Models\Friendship;
use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Unlockable;
use App\Models\User;
use App\Models\UserAchievement;
use App\Models\UserEloHistory;
use App\Models\UserUnlockable;
use Carbon\Carbon;

class GameCompletionService
{
    /**
     * Process all completion rewards for a finished game.
     * Returns a summary array for broadcasting.
     */
    public function processCompletion(Game $game): array
    {
        $summary = [
            'xp_awards' => [],
            'xp_details' => [],
            'coin_awards' => [],
            'level_ups' => [],
            'new_unlocks' => [],
            'elo_changes' => [],
            'achievements_unlocked' => [],
            'challenge_completed' => null,
        ];

        // Gather all users who participated
        $players = $game->players()->with('user')->get();
        $users = $players->pluck('user')->filter();

        foreach ($users as $user) {
            if (!$user) continue;

            // XP
            $xpResult = $this->awardXp($user, $game, $players);
            $summary['xp_awards'][$user->id] = $xpResult['xp'];
            $summary['xp_details'][$user->id] = [
                'old_xp' => $xpResult['old_xp'],
                'new_xp' => $xpResult['new_xp'],
                'old_level' => $xpResult['old_level'],
                'new_level' => $xpResult['new_level'],
            ];
            if ($xpResult['leveled_up']) {
                $summary['level_ups'][$user->id] = $xpResult['new_level'];
            }
            if (!empty($xpResult['new_unlocks'])) {
                $summary['new_unlocks'][$user->id] = $xpResult['new_unlocks'];
            }

            // Coins
            $coinResult = $this->awardCoins($user, $game, $players);
            $summary['coin_awards'][$user->id] = $coinResult;

            // Achievements
            $newAchievements = $this->checkAchievements($user, $game);
            if (!empty($newAchievements)) {
                $summary['achievements_unlocked'][$user->id] = $newAchievements;
            }

            // Daily challenge
            $challengeResult = $this->checkDailyChallenge($user, $game);
            if ($challengeResult) {
                $summary['challenge_completed'] = $challengeResult;
            }
        }

        // ELO (online duel only)
        if ($game->isOnline() && $game->isDuel()) {
            $summary['elo_changes'] = $this->updateElo($game, $players);
        }

        return $summary;
    }

    /**
     * Get progress toward an achievement for display purposes.
     * Returns [current, target] or null if not trackable.
     */
    public function getProgress(User $user, array $criteria): ?array
    {
        $type = $criteria['type'] ?? null;

        switch ($type) {
            case 'total_wins':
                $target = $criteria['count'] ?? 10;
                return ['current' => $this->getUserWins($user), 'target' => $target];

            case 'games_played':
                $target = $criteria['count'] ?? 50;
                return ['current' => $this->getUserGamesPlayed($user), 'target' => $target];

            case 'duel_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserDuelWins($user), 'target' => $target];

            case 'elo_reached':
                $target = $criteria['value'] ?? 1200;
                return ['current' => $user->elo_rating, 'target' => $target];

            case 'level_reached':
                $target = $criteria['value'] ?? 10;
                return ['current' => $user->level, 'target' => $target];

            case 'unique_characters':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUniqueCharactersUsed($user), 'target' => $target];

            case 'win_streak':
                $target = $criteria['count'] ?? 3;
                return ['current' => $this->getCurrentWinStreak($user), 'target' => $target];

            case 'login_streak':
                $target = $criteria['count'] ?? 3;
                return ['current' => $user->max_login_streak ?? 0, 'target' => $target];

            case 'online_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserOnlinePlays($user), 'target' => $target];

            case 'total_friends':
                $target = $criteria['count'] ?? 1;
                return ['current' => $this->getUserTotalFriends($user), 'target' => $target];

            case 'duel_plays':
                $target = $criteria['count'] ?? 10;
                return ['current' => $this->getUserDuelPlays($user), 'target' => $target];

            case 'solo_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserSoloPlays($user), 'target' => $target];

            case 'solo_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserSoloWins($user), 'target' => $target];

            case 'local_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserLocalPlays($user), 'target' => $target];

            case 'online_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserOnlineWins($user), 'target' => $target];

            case 'coop_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserCoopPlays($user), 'target' => $target];

            case 'coop_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserCoopWins($user), 'target' => $target];

            case 'wins_with_characters':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getUserCharacterWins($user), 'target' => $target];

            // Cross-product: mode + type combinations
            case 'single_classic_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'single', 'classic'), 'target' => $target];
            case 'single_classic_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'single', 'classic', true), 'target' => $target];
            case 'single_duel_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'single', 'duel'), 'target' => $target];
            case 'single_duel_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'single', 'duel', true), 'target' => $target];
            case 'local_classic_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'pass_and_play', 'classic'), 'target' => $target];
            case 'local_classic_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'pass_and_play', 'classic', true), 'target' => $target];
            case 'local_duel_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'pass_and_play', 'duel'), 'target' => $target];
            case 'local_duel_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'pass_and_play', 'duel', true), 'target' => $target];
            case 'online_classic_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'online', 'classic'), 'target' => $target];
            case 'online_classic_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'online', 'classic', true), 'target' => $target];
            case 'online_duel_plays':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'online', 'duel'), 'target' => $target];
            case 'online_duel_wins':
                $target = $criteria['count'] ?? 5;
                return ['current' => $this->getFilteredGameCount($user, 'online', 'duel', true), 'target' => $target];

            default:
                return null;
        }
    }

    private function getCurrentWinStreak(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        $recentGames = Game::where('status', 'completed')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        $streak = 0;
        foreach ($recentGames as $game) {
            $won = false;
            if ($game->isDuel()) {
                $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
                $won = $player && $player->player_number === $game->winner_player_number;
            } else {
                $won = (bool) $game->win;
            }

            if ($won) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    private function awardXp(User $user, Game $game, $players): array
    {
        $base = 50;
        $bonus = 0;

        // Determine if this user won
        $isWinner = $this->isWinner($user, $game, $players);

        if ($isWinner) {
            $bonus = $game->isDuel() ? 150 : 100;
        }

        $total = $base + $bonus;

        // Online multiplier
        if ($game->isOnline()) {
            $total = (int) ($total * 1.5);
        }

        $oldLevel = $user->level;
        $oldXp = $user->xp;
        $user->xp += $total;
        $user->level = User::calculateLevel($user->xp);
        $user->save();

        $leveledUp = $user->level > $oldLevel;
        $newUnlocks = [];

        // Check level-based unlockables on level up
        if ($leveledUp) {
            $newUnlocks = $this->checkLevelUnlockables($user);
        }

        return [
            'xp' => $total,
            'leveled_up' => $leveledUp,
            'new_level' => $user->level,
            'old_xp' => $oldXp,
            'old_level' => $oldLevel,
            'new_xp' => $user->xp,
            'new_unlocks' => $newUnlocks,
        ];
    }

    private function awardCoins(User $user, Game $game, $players): array
    {
        $base = 10;
        $bonus = 0;

        $isWinner = $this->isWinner($user, $game, $players);

        if ($isWinner) {
            $bonus = $game->isDuel() ? 25 : 15;
        }

        $total = $base + $bonus;

        if ($game->isOnline()) {
            $total = (int) ($total * 1.5);
        }

        $oldCoins = $user->coins;
        $user->coins += $total;
        $user->save();

        $user->recordCoinTransaction($total, 'earn', 'game', $game->id, 'Game completion reward');

        return [
            'coins' => $total,
            'old_coins' => $oldCoins,
            'new_coins' => $user->coins,
        ];
    }

    private function isWinner(User $user, Game $game, $players): bool
    {
        if ($game->isDuel()) {
            $player = $players->firstWhere('user_id', $user->id);
            return $player && $player->player_number === $game->winner_player_number;
        }
        return $game->win === true;
    }

    private function updateElo(Game $game, $players): array
    {
        $changes = [];
        $k = 32;

        $player1 = $players->firstWhere('player_number', 1);
        $player2 = $players->firstWhere('player_number', 2);

        if (!$player1?->user || !$player2?->user) {
            return $changes;
        }

        $user1 = $player1->user;
        $user2 = $player2->user;

        $r1 = $user1->elo_rating;
        $r2 = $user2->elo_rating;

        $e1 = 1 / (1 + pow(10, ($r2 - $r1) / 400));
        $e2 = 1 / (1 + pow(10, ($r1 - $r2) / 400));

        // Determine scores
        if ($game->winner_player_number === 1) {
            $s1 = 1;
            $s2 = 0;
        } elseif ($game->winner_player_number === 2) {
            $s1 = 0;
            $s2 = 1;
        } else {
            $s1 = 0.5;
            $s2 = 0.5;
        }

        $newR1 = (int) round($r1 + $k * ($s1 - $e1));
        $newR2 = (int) round($r2 + $k * ($s2 - $e2));

        // Ensure minimum of 0
        $newR1 = max(0, $newR1);
        $newR2 = max(0, $newR2);

        UserEloHistory::create([
            'user_id' => $user1->id,
            'game_id' => $game->id,
            'old_elo' => $r1,
            'new_elo' => $newR1,
        ]);

        UserEloHistory::create([
            'user_id' => $user2->id,
            'game_id' => $game->id,
            'old_elo' => $r2,
            'new_elo' => $newR2,
        ]);

        $user1->update(['elo_rating' => $newR1]);
        $user2->update(['elo_rating' => $newR2]);

        $changes[$user1->id] = ['old' => $r1, 'new' => $newR1, 'change' => $newR1 - $r1];
        $changes[$user2->id] = ['old' => $r2, 'new' => $newR2, 'change' => $newR2 - $r2];

        return $changes;
    }

    private function checkAchievements(User $user, Game $game): array
    {
        $unlocked = [];
        $earnedIds = UserAchievement::where('user_id', $user->id)->pluck('achievement_id')->toArray();
        $achievements = Achievement::whereNotIn('id', $earnedIds)->get();

        foreach ($achievements as $achievement) {
            if ($this->evaluateCriteria($user, $game, $achievement->criteria)) {
                UserAchievement::create([
                    'user_id' => $user->id,
                    'achievement_id' => $achievement->id,
                    'unlocked_at' => now(),
                ]);

                $unlocked[] = [
                    'id' => $achievement->id,
                    'name' => $achievement->name,
                    'description' => $achievement->description,
                    'icon' => $achievement->icon,
                ];

                // Grant unlockable reward if configured
                if ($achievement->reward_type === 'unlockable' && $achievement->reward_id) {
                    $this->grantUnlockable($user, $achievement->reward_id);
                }
            }
        }

        return $unlocked;
    }

    private function evaluateCriteria(User $user, Game $game, array $criteria): bool
    {
        $type = $criteria['type'] ?? null;

        switch ($type) {
            case 'win_streak':
                return $this->checkWinStreak($user, $criteria['count'] ?? 3);

            case 'total_wins':
                return $this->getUserWins($user) >= ($criteria['count'] ?? 10);

            case 'perfect_stats':
                return $this->checkPerfectStats($game, $criteria['count'] ?? 3);

            case 'games_played':
                return $this->getUserGamesPlayed($user) >= ($criteria['count'] ?? 50);

            case 'duel_wins':
                return $this->getUserDuelWins($user) >= ($criteria['count'] ?? 5);

            case 'elo_reached':
                return $user->elo_rating >= ($criteria['value'] ?? 1200);

            case 'level_reached':
                return $user->level >= ($criteria['value'] ?? 10);

            case 'unique_characters':
                return $this->getUniqueCharactersUsed($user) >= ($criteria['count'] ?? 5);

            case 'login_streak':
                return ($user->max_login_streak ?? 0) >= ($criteria['count'] ?? 3);

            case 'online_plays':
                return $this->getUserOnlinePlays($user) >= ($criteria['count'] ?? 5);

            case 'total_friends':
                return $this->getUserTotalFriends($user) >= ($criteria['count'] ?? 1);

            case 'duel_plays':
                return $this->getUserDuelPlays($user) >= ($criteria['count'] ?? 10);

            case 'solo_plays':
                return $this->getUserSoloPlays($user) >= ($criteria['count'] ?? 5);

            case 'solo_wins':
                return $this->getUserSoloWins($user) >= ($criteria['count'] ?? 5);

            case 'local_plays':
                return $this->getUserLocalPlays($user) >= ($criteria['count'] ?? 5);

            case 'online_wins':
                return $this->getUserOnlineWins($user) >= ($criteria['count'] ?? 5);

            case 'coop_plays':
                return $this->getUserCoopPlays($user) >= ($criteria['count'] ?? 5);

            case 'coop_wins':
                return $this->getUserCoopWins($user) >= ($criteria['count'] ?? 5);

            case 'all_stats_below':
                return $this->isWinner($user, $game, $game->players) && $this->checkAllStatsBelow($user, $game, $criteria['value'] ?? 5);

            case 'all_stats_above':
                return $this->isWinner($user, $game, $game->players) && $this->checkAllStatsAbove($user, $game, $criteria['value'] ?? 18);

            case 'wins_with_characters':
                return $this->getUserCharacterWins($user) >= ($criteria['count'] ?? 5);

            // Cross-product: mode + type combinations
            case 'single_classic_plays':
                return $this->getFilteredGameCount($user, 'single', 'classic') >= ($criteria['count'] ?? 5);
            case 'single_classic_wins':
                return $this->getFilteredGameCount($user, 'single', 'classic', true) >= ($criteria['count'] ?? 5);
            case 'single_duel_plays':
                return $this->getFilteredGameCount($user, 'single', 'duel') >= ($criteria['count'] ?? 5);
            case 'single_duel_wins':
                return $this->getFilteredGameCount($user, 'single', 'duel', true) >= ($criteria['count'] ?? 5);
            case 'local_classic_plays':
                return $this->getFilteredGameCount($user, 'pass_and_play', 'classic') >= ($criteria['count'] ?? 5);
            case 'local_classic_wins':
                return $this->getFilteredGameCount($user, 'pass_and_play', 'classic', true) >= ($criteria['count'] ?? 5);
            case 'local_duel_plays':
                return $this->getFilteredGameCount($user, 'pass_and_play', 'duel') >= ($criteria['count'] ?? 5);
            case 'local_duel_wins':
                return $this->getFilteredGameCount($user, 'pass_and_play', 'duel', true) >= ($criteria['count'] ?? 5);
            case 'online_classic_plays':
                return $this->getFilteredGameCount($user, 'online', 'classic') >= ($criteria['count'] ?? 5);
            case 'online_classic_wins':
                return $this->getFilteredGameCount($user, 'online', 'classic', true) >= ($criteria['count'] ?? 5);
            case 'online_duel_plays':
                return $this->getFilteredGameCount($user, 'online', 'duel') >= ($criteria['count'] ?? 5);
            case 'online_duel_wins':
                return $this->getFilteredGameCount($user, 'online', 'duel', true) >= ($criteria['count'] ?? 5);

            case 'no_stat_above':
                return $this->isWinner($user, $game, $game->players) && $this->checkNoStatAbove($user, $game, $criteria['value'] ?? 10);

            case 'total_score_above':
                return $this->checkTotalScoreAbove($user, $game, $criteria['value'] ?? 100);

            case 'total_score_below':
                return $this->isWinner($user, $game, $game->players) && $this->checkTotalScoreBelow($user, $game, $criteria['value'] ?? 40);

            default:
                return false;
        }
    }

    private function checkWinStreak(User $user, int $count): bool
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        $recentGames = Game::where('status', 'completed')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->orderByDesc('updated_at')
            ->limit($count)
            ->get();

        if ($recentGames->count() < $count) return false;

        foreach ($recentGames as $game) {
            if ($game->isDuel()) {
                $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
                if (!$player || $player->player_number !== $game->winner_player_number) return false;
            } else {
                if (!$game->win) return false;
            }
        }

        return true;
    }

    private function getUserWins(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        // Coop wins
        $coopWins = Game::where('status', 'completed')
            ->where('game_type', '!=', 'duel')
            ->where('win', true)
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();

        // Duel wins
        $duelWins = $this->getUserDuelWins($user);

        return $coopWins + $duelWins;
    }

    private function getUserDuelWins(User $user): int
    {
        $duelGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where('game_type', 'duel')
            ->whereIn('id', $duelGameIds)
            ->where(function ($q) use ($user) {
                $q->whereHas('players', function ($pq) use ($user) {
                    $pq->where('user_id', $user->id)
                        ->whereColumn('player_number', 'games.winner_player_number');
                });
            })
            ->count();
    }

    private function getUserGamesPlayed(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();
    }

    private function checkPerfectStats(Game $game, int $count): bool
    {
        if ($game->isDuel()) {
            // Check each kingdom
            foreach ($game->playerKingdoms as $kingdom) {
                $atMax = 0;
                foreach (['wealth', 'influence', 'security', 'religion', 'food', 'happiness'] as $stat) {
                    if ($kingdom->{$stat} >= 20) $atMax++;
                }
                if ($atMax >= $count) return true;
            }
            return false;
        }

        $atMax = 0;
        foreach (['wealth', 'influence', 'security', 'religion', 'food', 'happiness'] as $stat) {
            if ($game->{$stat} >= 20) $atMax++;
        }
        return $atMax >= $count;
    }

    private function getUniqueCharactersUsed(User $user): int
    {
        return GamePlayer::where('user_id', $user->id)
            ->whereNotNull('character_id')
            ->distinct('character_id')
            ->count('character_id');
    }

    private function grantUnlockable(User $user, int $unlockableId): void
    {
        $exists = UserUnlockable::where('user_id', $user->id)
            ->where('unlockable_id', $unlockableId)
            ->exists();

        if (!$exists) {
            UserUnlockable::create([
                'user_id' => $user->id,
                'unlockable_id' => $unlockableId,
                'unlocked_at' => now(),
            ]);
        }
    }

    private function checkLevelUnlockables(User $user): array
    {
        $levelUnlockables = Unlockable::where('unlock_method', 'level')
            ->where('unlock_value', '<=', $user->level)
            ->get();

        $alreadyUnlocked = UserUnlockable::where('user_id', $user->id)
            ->pluck('unlockable_id')
            ->toArray();

        $newUnlocks = [];
        foreach ($levelUnlockables as $unlockable) {
            if (!in_array($unlockable->id, $alreadyUnlocked)) {
                UserUnlockable::create([
                    'user_id' => $user->id,
                    'unlockable_id' => $unlockable->id,
                    'unlocked_at' => now(),
                ]);
                $newUnlocks[] = [
                    'id' => $unlockable->id,
                    'name' => $unlockable->name,
                    'type' => $unlockable->type,
                ];
            }
        }

        return $newUnlocks;
    }

    private function getUserOnlinePlays(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where('game_mode', 'online')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();
    }

    private function getUserTotalFriends(User $user): int
    {
        return Friendship::where('status', 'accepted')
            ->where(function ($q) use ($user) {
                $q->where('sender_id', $user->id)->orWhere('receiver_id', $user->id);
            })
            ->count();
    }

    private function getUserDuelPlays(User $user): int
    {
        $duelGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where('game_type', 'duel')
            ->whereIn('id', $duelGameIds)
            ->count();
    }

    private function getUserSoloPlays(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where('game_mode', 'single')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();
    }

    private function getUserSoloWins(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where('game_mode', 'single')
            ->where('win', true)
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();
    }

    private function getUserLocalPlays(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where('game_mode', 'pass_and_play')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();
    }

    private function getUserOnlineWins(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        // Coop online wins
        $coopWins = Game::where('status', 'completed')
            ->where('game_mode', 'online')
            ->where('game_type', '!=', 'duel')
            ->where('win', true)
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();

        // Duel online wins
        $duelWins = Game::where('status', 'completed')
            ->where('game_mode', 'online')
            ->where('game_type', 'duel')
            ->whereIn('id', $participantGameIds)
            ->where(function ($q) use ($user) {
                $q->whereHas('players', function ($pq) use ($user) {
                    $pq->where('user_id', $user->id)
                        ->whereColumn('player_number', 'games.winner_player_number');
                });
            })
            ->count();

        return $coopWins + $duelWins;
    }

    private function getUserCoopPlays(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where('game_type', '!=', 'duel')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();
    }

    private function getUserCoopWins(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        return Game::where('status', 'completed')
            ->where('game_type', '!=', 'duel')
            ->where('win', true)
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->count();
    }

    /**
     * Generic helper for counting games by mode + type combination.
     * @param string|null $mode 'single', 'pass_and_play', 'online', or null for any
     * @param string|null $type 'duel', 'classic' (non-duel), or null for any
     * @param bool $winOnly Only count wins
     */
    private function getFilteredGameCount(User $user, ?string $mode, ?string $type, bool $winOnly = false): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        $query = Game::where('status', 'completed')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            });

        if ($mode) {
            $query->where('game_mode', $mode);
        }

        if ($type === 'duel') {
            $query->where('game_type', 'duel');
        } elseif ($type === 'classic') {
            $query->where('game_type', '!=', 'duel');
        }

        if ($winOnly) {
            if ($type === 'duel') {
                $query->whereIn('id', $participantGameIds)
                    ->whereHas('players', function ($pq) use ($user) {
                        $pq->where('user_id', $user->id)
                            ->whereColumn('player_number', 'games.winner_player_number');
                    });
            } else {
                $query->where('win', true);
            }
        }

        return $query->count();
    }

    private function getUserCharacterWins(User $user): int
    {
        $participantGameIds = GamePlayer::where('user_id', $user->id)->pluck('game_id');

        // Get all completed games this user won
        $wonGameIds = Game::where('status', 'completed')
            ->where(function ($q) use ($user, $participantGameIds) {
                $q->where('user_id', $user->id)->orWhereIn('id', $participantGameIds);
            })
            ->get()
            ->filter(function ($game) use ($user) {
                if ($game->isDuel()) {
                    $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
                    return $player && $player->player_number === $game->winner_player_number;
                }
                return (bool) $game->win;
            })
            ->pluck('id');

        return GamePlayer::where('user_id', $user->id)
            ->whereIn('game_id', $wonGameIds)
            ->whereNotNull('character_id')
            ->distinct('character_id')
            ->count('character_id');
    }

    private function checkAllStatsBelow(User $user, Game $game, int $value): bool
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        if ($game->isDuel()) {
            $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
            if (!$player) return false;
            $kingdom = $game->playerKingdoms()->where('game_player_id', $player->id)->first();
            if (!$kingdom) return false;
            foreach ($stats as $s) {
                if ($kingdom->{$s} >= $value) return false;
            }
            return true;
        }
        foreach ($stats as $s) {
            if ($game->{$s} >= $value) return false;
        }
        return true;
    }

    private function checkAllStatsAbove(User $user, Game $game, int $value): bool
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        if ($game->isDuel()) {
            $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
            if (!$player) return false;
            $kingdom = $game->playerKingdoms()->where('game_player_id', $player->id)->first();
            if (!$kingdom) return false;
            foreach ($stats as $s) {
                if ($kingdom->{$s} <= $value) return false;
            }
            return true;
        }
        foreach ($stats as $s) {
            if ($game->{$s} <= $value) return false;
        }
        return true;
    }

    private function checkNoStatAbove(User $user, Game $game, int $value): bool
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        if ($game->isDuel()) {
            $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
            if (!$player) return false;
            $kingdom = $game->playerKingdoms()->where('game_player_id', $player->id)->first();
            if (!$kingdom) return false;
            foreach ($stats as $s) {
                if ($kingdom->{$s} > $value) return false;
            }
            return true;
        }
        foreach ($stats as $s) {
            if ($game->{$s} > $value) return false;
        }
        return true;
    }

    private function checkTotalScoreAbove(User $user, Game $game, int $value): bool
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        if ($game->isDuel()) {
            $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
            if (!$player) return false;
            $kingdom = $game->playerKingdoms()->where('game_player_id', $player->id)->first();
            if (!$kingdom) return false;
            $total = 0;
            foreach ($stats as $s) $total += $kingdom->{$s};
            return $total > $value;
        }
        $total = 0;
        foreach ($stats as $s) $total += $game->{$s};
        return $total > $value;
    }

    private function checkTotalScoreBelow(User $user, Game $game, int $value): bool
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        if ($game->isDuel()) {
            $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
            if (!$player) return false;
            $kingdom = $game->playerKingdoms()->where('game_player_id', $player->id)->first();
            if (!$kingdom) return false;
            $total = 0;
            foreach ($stats as $s) $total += $kingdom->{$s};
            return $total < $value;
        }
        $total = 0;
        foreach ($stats as $s) $total += $game->{$s};
        return $total < $value;
    }

    private function checkDailyChallenge(User $user, Game $game): ?array
    {
        $today = Carbon::today();
        $challenge = DailyChallenge::where('date', $today)->first();
        if (!$challenge) return null;

        // Already completed?
        $entry = DailyChallengeEntry::where('user_id', $user->id)
            ->where('daily_challenge_id', $challenge->id)
            ->first();

        if ($entry && $entry->completed_at) return null;

        // Check criteria
        if (!$this->evaluateChallengeCriteria($user, $game, $challenge->criteria)) {
            return null;
        }

        // Mark completed
        if ($entry) {
            $entry->update([
                'game_id' => $game->id,
                'completed_at' => now(),
            ]);
        } else {
            DailyChallengeEntry::create([
                'user_id' => $user->id,
                'daily_challenge_id' => $challenge->id,
                'game_id' => $game->id,
                'completed_at' => now(),
            ]);
        }

        // Award bonus XP
        $user->xp += $challenge->reward_xp;
        $user->level = User::calculateLevel($user->xp);
        $user->save();

        return [
            'challenge_id' => $challenge->id,
            'title' => $challenge->title,
            'reward_xp' => $challenge->reward_xp,
        ];
    }

    private function evaluateChallengeCriteria(User $user, Game $game, array $criteria): bool
    {
        $type = $criteria['type'] ?? null;

        switch ($type) {
            case 'play_game':
                $mode = $criteria['mode'] ?? 'any';
                return $mode === 'any' || $game->game_mode === $mode;

            case 'win_game':
                $mode = $criteria['mode'] ?? 'any';
                $modeMatch = $mode === 'any' || $game->game_mode === $mode;
                $isWin = $game->isDuel()
                    ? GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->where('player_number', $game->winner_player_number)->exists()
                    : $game->win;
                return $modeMatch && $isWin;

            case 'stat_threshold':
                $stat = $criteria['stat'] ?? 'wealth';
                $value = $criteria['value'] ?? 15;
                if ($game->isDuel()) {
                    $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
                    if (!$player) return false;
                    $kingdom = $game->playerKingdoms()->where('game_player_id', $player->id)->first();
                    return $kingdom && $kingdom->{$stat} >= $value;
                }
                return $game->{$stat} >= $value;

            case 'use_character':
                $characterId = $criteria['character_id'] ?? null;
                return GamePlayer::where('game_id', $game->id)
                    ->where('user_id', $user->id)
                    ->where('character_id', $characterId)
                    ->exists();

            case 'no_stat_below':
                $value = $criteria['value'] ?? 8;
                $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
                if ($game->isDuel()) {
                    $player = GamePlayer::where('game_id', $game->id)->where('user_id', $user->id)->first();
                    if (!$player) return false;
                    $kingdom = $game->playerKingdoms()->where('game_player_id', $player->id)->first();
                    if (!$kingdom) return false;
                    foreach ($stats as $s) {
                        if ($kingdom->{$s} < $value) return false;
                    }
                    return true;
                }
                foreach ($stats as $s) {
                    if ($game->{$s} < $value) return false;
                }
                return true;

            default:
                return false;
        }
    }
}
