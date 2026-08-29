<?php

namespace App\Services;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\GamePlayerHand;
use App\Models\GamePlayerKingdom;
use Illuminate\Support\Collection;

class BotService
{
    /**
     * Pick which of a cooperative round's cards to attempt (the "positive" role): the
     * card we bet on succeeding for its upside, leaving the rest as negative-only.
     *
     * The choice is weighted-random rather than always-best so that repeated simulations
     * of the identical seeded scenario explore the space of reasonable decisions a human
     * might make, producing a spread of outcomes rather than a single deterministic result.
     *
     * @param  Collection<int, GamePlayerHand>  $hands
     * @param  callable(): float  $random  Returns a float in [0, 1); lets the caller seed it.
     */
    public function pickCooperativePositiveHandId(Collection $hands, Game $game, callable $random): int
    {
        if ($hands->count() < 2) {
            return (int) $hands->first()->id;
        }

        // Softmax over card scores: better cards are likelier, but weaker cards remain
        // possible. Temperature controls how "sharp" the reasonable-player model is.
        $temperature = 4.0;
        $weights = $hands->mapWithKeys(fn (GamePlayerHand $hand): array => [
            (int) $hand->id => exp($this->scoreCooperativeCard($hand->card, $game) / $temperature),
        ]);

        $total = $weights->sum();
        if ($total <= 0.0) {
            return (int) $hands->random()->id;
        }

        $roll = $random() * $total;
        $cumulative = 0.0;
        foreach ($weights as $handId => $weight) {
            $cumulative += $weight;
            if ($roll < $cumulative) {
                return (int) $handId;
            }
        }

        return (int) $weights->keys()->last();
    }

    /**
     * Score a card for the cooperative kingdom (the Game itself holds the six stats),
     * biased toward the challenge's goal so the bot pursues what actually wins the run.
     */
    public function scoreCooperativeCard($card, Game $game): float
    {
        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        $goal = $game->dailyChallenge?->criteria['goal'] ?? [];
        $goalStats = $this->goalStats($goal);

        $weight = function (string $stat) use ($game, $goalStats): float {
            $value = (int) ($game->{$stat} ?? 8);
            $base = match (true) {
                $value <= 2 => 10.0,
                $value <= 5 => 6.0,
                $value <= 8 => 3.0,
                $value <= 12 => 1.5,
                $value >= 18 => 2.0,
                default => 1.0,
            };

            // A stat named in the win condition matters more; one that is safe and not part
            // of the goal barely matters.
            return in_array($stat, $goalStats, true) ? $base * 2.5 : $base;
        };

        $score = 0.0;

        $positive = $card->positive_effects ?? [];
        foreach ($positive as $key => $value) {
            if (in_array($key, $stats, true) && is_numeric($value)) {
                $score += $value * $weight($key) * 0.6;
            }
        }

        $negative = $card->negative_effects ?? [];
        foreach ($negative as $key => $value) {
            if (in_array($key, $stats, true) && is_numeric($value)) {
                $score += $value * $weight($key);
            }
        }

        if (! empty($positive['recover_die'])) {
            $score += 2.0;
        }
        if (! empty($negative['lose_die'])) {
            $score -= 5.0;
        }

        // Prefer attempting the easier card, all else equal — a lower difficulty is likelier
        // to succeed and unlock its positives.
        $score -= (float) ($card->difficulty ?? 5) * 0.3;

        return $score;
    }

    /**
     * The stat keys that a challenge goal cares about, so the bot can prioritise them.
     *
     * @param  array<string, mixed>  $goal
     * @return list<string>
     */
    private function goalStats(array $goal): array
    {
        return match ($goal['type'] ?? null) {
            'stat_threshold' => [(string) ($goal['stat'] ?? 'wealth')],
            'stat_threshold_all' => array_keys($goal['targets'] ?? []),
            'no_stat_below' => ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'],
            default => [],
        };
    }

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
        if (! $kingdom) {
            return 0;
        }

        $stats = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        $score = 0;

        $weight = function (string $stat) use ($kingdom) {
            $val = $kingdom->{$stat} ?? 15;
            if ($val <= 2) {
                return 10;
            }
            if ($val <= 5) {
                return 6;
            }
            if ($val <= 8) {
                return 3;
            }
            if ($val <= 12) {
                return 1.5;
            }
            if ($val >= 18) {
                return 2;
            } // Close to winning condition

            return 1;
        };

        // Positive effects (on success)
        $posEffects = $card->positive_effects ?? [];
        foreach ($posEffects as $key => $val) {
            if (! in_array($key, $stats) || ! is_numeric($val)) {
                continue;
            }
            $score += $val * $weight($key) * 0.6; // Discounted by success probability
        }

        // Negative effects (always apply)
        $negEffects = $card->negative_effects ?? [];
        foreach ($negEffects as $key => $val) {
            if (! in_array($key, $stats) || ! is_numeric($val)) {
                continue;
            }
            $score += $val * $weight($key); // Full weight since always applies
        }

        // Special effects
        if (! empty($posEffects['recover_die'])) {
            $score += 2;
        }
        if (! empty($posEffects['draw_item'])) {
            $score += 1;
        }
        if (! empty($negEffects['lose_die'])) {
            $score -= 5;
        }

        // Factor in difficulty
        $difficulty = $card->difficulty ?? 5;
        $score -= $difficulty * 0.2;

        return $score;
    }
}
