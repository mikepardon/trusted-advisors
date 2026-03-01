<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerHand;
use App\Models\GamePlayerKingdom;

class BotService
{
    /**
     * Decide which card to reveal during the offering phase.
     * Returns the hand_id the bot wants to reveal.
     */
    public function decideDuelOffer(Game $game, GamePlayer $bot): int
    {
        $hands = GamePlayerHand::where('game_id', $game->id)
            ->where('game_player_id', $bot->id)
            ->where('round_number', $game->current_round)
            ->with('card')
            ->get();

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

        if ($difficulty === 'hard') {
            // Hard: reveal the weaker card (keep the strong one hidden, hoping opponent picks the revealed weaker card)
            return $scores->sortBy(fn ($s) => $s)->keys()->first();
        }

        // Medium: reveal the weaker card (straightforward — keep better card for self)
        return $scores->sortBy(fn ($s) => $s)->keys()->first();
    }

    /**
     * Decide which card to choose during the choosing phase.
     * Returns the hand_id the bot wants to pick.
     */
    public function decideDuelChoice(Game $game, GamePlayer $bot): int
    {
        $hands = GamePlayerHand::where('game_id', $game->id)
            ->where('round_number', $game->current_round)
            ->with('card')
            ->get();

        // The offerer has revealed one card. The bot (as chooser) sees the revealed card.
        // The other card is hidden.
        $offerer = $game->players()->where('player_number', $game->offerer_player_number)->first();
        $offererHands = $hands->where('game_player_id', $offerer->id);

        $revealedHand = $offererHands->firstWhere('is_revealed', true);
        $hiddenHand = $offererHands->firstWhere('is_revealed', false);

        if (!$revealedHand || !$hiddenHand) {
            return $offererHands->first()->id;
        }

        $difficulty = $bot->bot_difficulty ?? 'medium';

        if ($difficulty === 'easy') {
            // Easy: random pick
            return collect([$revealedHand, $hiddenHand])->random()->id;
        }

        $kingdom = GamePlayerKingdom::where('game_id', $game->id)
            ->where('game_player_id', $bot->id)
            ->first();

        $revealedScore = $this->scoreCardForKingdom($revealedHand->card, $kingdom);

        if ($difficulty === 'hard') {
            // Hard: evaluate revealed card against expected value of hidden card
            // If revealed card is good, take it; otherwise gamble on hidden
            $avgScore = 0; // Unknown card has neutral expected value
            return $revealedScore >= $avgScore ? $revealedHand->id : $hiddenHand->id;
        }

        // Medium: pick the revealed card if it helps the weakest stat, otherwise take the gamble
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        $weakestStat = null;
        $weakestVal = 21;
        if ($kingdom) {
            foreach ($stats as $s) {
                if ($kingdom->{$s} < $weakestVal) {
                    $weakestVal = $kingdom->{$s};
                    $weakestStat = $s;
                }
            }
        }

        // Check if revealed card helps the weakest stat
        $posEffects = $revealedHand->card->positive_effects ?? [];
        if ($weakestStat && isset($posEffects[$weakestStat]) && $posEffects[$weakestStat] > 0) {
            return $revealedHand->id;
        }

        // If revealed card score is positive, take it
        return $revealedScore > 0 ? $revealedHand->id : $hiddenHand->id;
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
