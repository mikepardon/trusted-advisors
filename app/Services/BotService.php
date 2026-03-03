<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerHand;
use App\Models\GamePlayerKingdom;

class BotService
{
    /**
     * Decide which card to keep during the simultaneous selection phase.
     * Returns the hand_id the bot wants to keep (the other is sent to opponent).
     */
    public function decideDuelSelect(Game $game, GamePlayer $bot): int
    {
        $hands = GamePlayerHand::where('game_id', $game->id)
            ->where('game_player_id', $bot->id)
            ->where('round_number', $game->current_round)
            ->with('card')
            ->get();

        if ($hands->isEmpty()) {
            throw new \RuntimeException("Bot player #{$bot->id} has no cards for round {$game->current_round}");
        }

        if ($hands->count() < 2) {
            return $hands->first()->id;
        }

        $difficulty = $bot->bot_difficulty ?? 'medium';

        if ($difficulty === 'easy') {
            return $hands->random()->id;
        }

        $kingdom = GamePlayerKingdom::where('game_id', $game->id)
            ->where('game_player_id', $bot->id)
            ->first();

        $scores = $hands->mapWithKeys(function ($hand) use ($kingdom) {
            return [$hand->id => $this->scoreCardForKingdom($hand->card, $kingdom)];
        });

        // Keep the card with the higher score (better for self)
        return $scores->sortByDesc(fn ($s) => $s)->keys()->first();
    }

    /**
     * Score a card based on how it affects a kingdom.
     * Higher score = better card for the player.
     */
    public function scoreCardForKingdom($card, ?GamePlayerKingdom $kingdom): float
    {
        if (!$kingdom) return 0;

        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        $score = 0;

        $weight = function (string $stat) use ($kingdom) {
            $val = $kingdom->{$stat} ?? 15;
            if ($val <= 2) return 10;
            if ($val <= 5) return 6;
            if ($val <= 8) return 3;
            if ($val <= 12) return 1.5;
            if ($val >= 18) return 2; // Close to winning condition
            return 1;
        };

        // Positive effects (on success)
        $posEffects = $card->positive_effects ?? [];
        foreach ($posEffects as $key => $val) {
            if (!in_array($key, $stats) || !is_numeric($val)) continue;
            $score += $val * $weight($key) * 0.6; // Discounted by success probability
        }

        // Negative effects (always apply)
        $negEffects = $card->negative_effects ?? [];
        foreach ($negEffects as $key => $val) {
            if (!in_array($key, $stats) || !is_numeric($val)) continue;
            $score += $val * $weight($key); // Full weight since always applies
        }

        // Special effects
        if (!empty($posEffects['recover_die'])) $score += 2;
        if (!empty($posEffects['draw_item'])) $score += 1;
        if (!empty($negEffects['lose_die'])) $score -= 5;

        // Factor in difficulty
        $difficulty = $card->difficulty ?? 5;
        $score -= $difficulty * 0.2;

        return $score;
    }
}
