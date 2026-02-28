<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Character;
use App\Models\Event;
use App\Models\GameRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotGameController extends Controller
{
    private array $allCards;
    private array $allEvents;
    private array $allCharacters;
    private array $diceRules;

    public function simulate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'num_games' => 'required|integer|min:1|max:1000',
            'num_players' => 'required|integer|min:1|max:6',
            'total_rounds' => 'required|integer|min:1|max:48',
            'starting_stats' => 'sometimes|integer|min:3|max:15',
            'negative_multiplier' => 'sometimes|numeric|min:0.5|max:3.0',
        ]);

        $numGames = $validated['num_games'];
        $numPlayers = $validated['num_players'];
        $totalRounds = $validated['total_rounds'];
        $startingStats = $validated['starting_stats'] ?? 10;
        $negativeMultiplier = $validated['negative_multiplier'] ?? 1.0;

        // Preload all game data
        $this->allCards = Card::all()->toArray();
        $this->allEvents = Event::orderBy('id')->get()->toArray();
        $this->allCharacters = Character::all()->toArray();
        $this->diceRules = GameRule::getValue('dice_per_player_count', []);

        if (empty($this->allCards) || empty($this->allCharacters)) {
            return response()->json(['error' => 'No cards or characters in database'], 422);
        }

        $results = [];
        for ($i = 0; $i < $numGames; $i++) {
            $results[] = $this->runGame($numPlayers, $totalRounds, $startingStats, $negativeMultiplier);
        }

        return response()->json($this->aggregateResults($results, $numGames));
    }

    private function runGame(int $numPlayers, int $totalRounds, int $startingStats = 10, float $negativeMultiplier = 1.0): array
    {
        // Pick random characters
        $characters = collect($this->allCharacters)->shuffle()->take($numPlayers)->values()->all();

        // Init kingdom stats
        $stats = ['wealth' => $startingStats, 'influence' => $startingStats, 'security' => $startingStats, 'religion' => $startingStats, 'food' => $startingStats, 'happiness' => $startingStats];

        // Build shuffled card deck
        $deck = collect($this->allCards)->shuffle()->values()->all();
        $deckPos = 0;

        // Shuffle events per game so each game is unique
        $shuffledEvents = collect($this->allEvents)->shuffle()->values()->all();

        // Init player state
        $players = [];
        foreach ($characters as $i => $char) {
            $players[] = [
                'player_number' => $i + 1,
                'character' => $char,
                'lost_dice' => 0,
            ];
        }

        $roundsSurvived = 0;
        $gameOverReason = null;
        $roundLog = [];

        for ($round = 1; $round <= $totalRounds; $round++) {
            // Get event for this round (from shuffled per-game events)
            $eventIndex = (int) floor(($round - 1) / 7);
            $event = $shuffledEvents[$eventIndex] ?? null;

            // Cards per player
            $cardsPerPlayer = $this->getCardsPerPlayer($event);

            // Deal cards
            $playerHands = [];
            foreach ($players as $player) {
                $hand = [];
                for ($c = 0; $c < $cardsPerPlayer; $c++) {
                    $cardIdx = $deckPos % count($this->allCards);
                    $hand[] = $deck[$cardIdx] ?? $this->allCards[array_rand($this->allCards)];
                    $deckPos++;
                }
                $playerHands[$player['player_number']] = $hand;
            }

            // Bot picks: choose which card to act on (positive) vs leave unattended (negative)
            // Strategy: keep the kingdom balanced — prioritize helping weak stats,
            // avoid damaging weak stats, factor in difficulty vs dice power
            $positiveCards = [];
            $negativeCards = [];

            // Estimate total dice power for difficulty assessment
            $baseDice = $this->diceRules[(string) $numPlayers] ?? 3;
            $avgRollPerDie = 3.5; // rough average per die face
            $totalActiveDice = 0;
            foreach ($players as $p) {
                $totalActiveDice += max(1, $baseDice - $p['lost_dice']);
            }
            $expectedRoll = $totalActiveDice * $avgRollPerDie;

            foreach ($playerHands as $pNum => $hand) {
                $bestIdx = 0;
                $bestScore = -99999;

                foreach ($hand as $idx => $card) {
                    $score = $this->scoreCardChoice($card, $stats, $expectedRoll, 'positive');
                    if ($score > $bestScore) {
                        $bestScore = $score;
                        $bestIdx = $idx;
                    }
                }

                foreach ($hand as $idx => $card) {
                    if ($idx === $bestIdx) {
                        $positiveCards[] = $card;
                    } else {
                        $negativeCards[] = $card;
                    }
                }
            }

            // === POSITIVE PHASE ===
            $totalDifficulty = array_sum(array_column($positiveCards, 'difficulty'));
            $totalDifficulty = max(1, $totalDifficulty);

            // Event reduce_dice
            $tempDiceReduction = 0;
            if ($event && ($event['mechanic'] ?? '') === 'reduce_dice') {
                $tempDiceReduction = $event['mechanic_data']['amount'] ?? 0;
            }

            // Roll dice
            $baseDice = $this->diceRules[(string) $numPlayers] ?? 3;
            $totalRoll = 0;
            $wildTriggers = [];

            foreach ($players as &$player) {
                $dice = $player['character']['dice'];
                $activeDice = max(1, $baseDice - $player['lost_dice'] - $tempDiceReduction);

                for ($d = 0; $d < $activeDice && $d < count($dice); $d++) {
                    $die = $dice[$d];
                    $face = $die[random_int(0, 5)];

                    if ($face === 'WILD') {
                        $wildValue = $player['character']['wild_value'];
                        $totalRoll += $wildValue;
                        $wildTriggers[] = [
                            'character_name' => $player['character']['name'],
                            'wild_value' => $wildValue,
                            'ability' => $player['character']['wild_ability'] ?? '',
                        ];
                    } else {
                        $totalRoll += (int) $face;
                    }
                }
            }
            unset($player);

            // Process wild abilities
            foreach ($wildTriggers as $trigger) {
                switch ($trigger['ability'] ?? '') {
                    case 'inspire':
                        $totalRoll += $numPlayers;
                        break;
                    case 'rally':
                        $totalRoll += 2;
                        break;
                    case 'divine':
                        $totalRoll += $trigger['wild_value'];
                        break;
                    case 'gamble':
                        $totalRoll += random_int(-3, 5);
                        break;
                    case 'wisdom':
                        $totalRoll += 2;
                        break;
                }
            }

            $positiveSuccess = $totalRoll >= $totalDifficulty;

            // Collect effects
            $positiveEffects = [];
            if ($positiveSuccess) {
                foreach ($positiveCards as $card) {
                    foreach (($card['positive_effects'] ?? []) as $stat => $change) {
                        if (!is_numeric($change)) continue;
                        $positiveEffects[$stat] = ($positiveEffects[$stat] ?? 0) + $change;
                    }
                }
            }

            $negativeEffects = [];
            foreach ($negativeCards as $card) {
                foreach (($card['negative_effects'] ?? []) as $stat => $change) {
                    if ($stat === 'lose_die') {
                        $target = array_rand($players);
                        if ($players[$target]['lost_dice'] < 2) {
                            $players[$target]['lost_dice']++;
                        }
                        continue;
                    }
                    if (!is_numeric($change)) continue;
                    // Apply negative multiplier (amplifies negative effects for balance testing)
                    $scaledChange = (int) round($change * $negativeMultiplier);
                    $negativeEffects[$stat] = ($negativeEffects[$stat] ?? 0) + $scaledChange;
                }
            }

            // Apply effects
            $statKeys = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
            $combinedEffects = [];
            foreach ($statKeys as $s) {
                $total = ($positiveEffects[$s] ?? 0) + ($negativeEffects[$s] ?? 0);
                if ($total !== 0) {
                    $combinedEffects[$s] = $total;
                    $stats[$s] = max(0, min(20, $stats[$s] + $total));
                }
            }

            // Apply event stat modifiers (always apply each round)
            if ($event && !empty($event['stat_modifiers'])) {
                foreach ($event['stat_modifiers'] as $stat => $change) {
                    if (in_array($stat, $statKeys) && is_numeric($change)) {
                        $stats[$stat] = max(0, min(20, $stats[$stat] + $change));
                    }
                }
            }

            $roundsSurvived = $round;

            $roundLog[] = [
                'round' => $round,
                'success' => $positiveSuccess,
                'roll' => $totalRoll,
                'difficulty' => $totalDifficulty,
                'stats' => [...$stats],
            ];

            // Check game over
            foreach ($statKeys as $s) {
                if ($stats[$s] <= 0) {
                    $gameOverReason = "{$s} collapsed to zero";
                    break 2;
                }
            }
        }

        $totalScore = array_sum($stats);
        $win = $gameOverReason === null;

        return [
            'win' => $win,
            'rounds_survived' => $roundsSurvived,
            'total_score' => $totalScore,
            'final_stats' => $stats,
            'game_over_reason' => $gameOverReason,
            'round_log' => $roundLog,
        ];
    }

    /**
     * Score a card choice based on current kingdom state.
     *
     * Core idea: the bot wants to survive, not maximise score.
     * - Positive effects on LOW stats are extremely valuable
     * - Negative effects on LOW stats are extremely dangerous
     * - Difficulty matters: a card you can't beat gives 0 positive value
     * - Special hazards (lose_die) get heavy penalties
     */
    private function scoreCardChoice(array $card, array $stats, float $expectedRoll, string $role): float
    {
        $statKeys = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];
        $score = 0;

        // Weight multiplier based on how endangered a stat is
        // stat=1 → weight 10, stat=5 → weight 4, stat=10 → weight 1, stat=15+ → weight 0.5
        $weight = function (string $stat) use ($stats) {
            $val = $stats[$stat] ?? 10;
            if ($val <= 2) return 10;
            if ($val <= 4) return 6;
            if ($val <= 6) return 4;
            if ($val <= 8) return 2.5;
            if ($val <= 10) return 1.5;
            if ($val <= 14) return 1;
            return 0.5; // stat is healthy, low priority
        };

        // Estimate success probability based on difficulty vs expected roll
        $difficulty = $card['difficulty'] ?? 5;
        // Rough sigmoid: if expectedRoll >> difficulty, ~1.0; if <<, ~0.0
        $margin = $expectedRoll - $difficulty;
        $successProb = max(0.05, min(0.95, 0.5 + $margin * 0.1));

        // Score positive effects (only apply if we succeed)
        $posEffects = $card['positive_effects'] ?? [];
        foreach ($posEffects as $key => $val) {
            if (!in_array($key, $statKeys) || !is_numeric($val)) continue;
            // Positive change on a low stat is gold; on a high stat is meh
            if ($val > 0) {
                $score += $val * $weight($key) * $successProb;
            } else {
                // Rare: positive effect that's actually negative
                $score += $val * $weight($key) * $successProb;
            }
        }

        // Bonus for special positive effects
        if (!empty($posEffects['recover_die'])) $score += 3 * $successProb;
        if (!empty($posEffects['draw_item'])) $score += 1.5 * $successProb;
        if (!empty($posEffects['remove_curse'])) $score += 2 * $successProb;

        // Score negative effects (ALWAYS apply regardless of success)
        $negEffects = $card['negative_effects'] ?? [];
        foreach ($negEffects as $key => $val) {
            if (!in_array($key, $statKeys) || !is_numeric($val)) continue;
            // Negative change on a low stat is catastrophic
            $score += $val * $weight($key); // val is negative, weight is positive → big negative score
        }

        // Heavy penalty for special negative effects
        if (!empty($negEffects['lose_die'])) $score -= 8;
        if (!empty($negEffects['discard_item'])) $score -= 3;

        // Penalize high difficulty cards (less likely to succeed = less positive value)
        // Already handled via successProb above, but add a small direct penalty
        // to prefer easier cards when positive effects are similar
        $score -= $difficulty * 0.3;

        return $score;
    }

    private function getCardsPerPlayer(?array $event): int
    {
        if ($event && ($event['mechanic'] ?? '') === 'altered_deal') {
            $positive = $event['mechanic_data']['positive_cards'] ?? 1;
            $negative = $event['mechanic_data']['negative_cards'] ?? 1;
            return $positive + $negative;
        }
        return 2;
    }

    private function aggregateResults(array $results, int $numGames): array
    {
        $wins = 0;
        $totalScore = 0;
        $totalRounds = 0;
        $collapseReasons = [];
        $statTotals = ['wealth' => 0, 'influence' => 0, 'security' => 0, 'religion' => 0, 'food' => 0, 'happiness' => 0];
        $scoreDistribution = [];
        $roundDistribution = [];

        // Track per-round stats for average stat trajectory
        $roundStats = [];

        foreach ($results as $r) {
            if ($r['win']) $wins++;
            $totalScore += $r['total_score'];
            $totalRounds += $r['rounds_survived'];
            foreach ($r['final_stats'] as $stat => $val) {
                $statTotals[$stat] += $val;
            }
            if ($r['game_over_reason']) {
                $reason = $r['game_over_reason'];
                $collapseReasons[$reason] = ($collapseReasons[$reason] ?? 0) + 1;
            }

            // Score buckets
            $bucket = (int) floor($r['total_score'] / 10) * 10;
            $scoreDistribution[$bucket] = ($scoreDistribution[$bucket] ?? 0) + 1;

            // Round buckets
            $roundDistribution[$r['rounds_survived']] = ($roundDistribution[$r['rounds_survived']] ?? 0) + 1;

            // Per-round stat tracking
            foreach ($r['round_log'] as $entry) {
                $rnd = $entry['round'];
                if (!isset($roundStats[$rnd])) {
                    $roundStats[$rnd] = ['count' => 0, 'successes' => 0, 'wealth' => 0, 'influence' => 0, 'security' => 0, 'religion' => 0, 'food' => 0, 'happiness' => 0];
                }
                $roundStats[$rnd]['count']++;
                if ($entry['success']) $roundStats[$rnd]['successes']++;
                foreach ($entry['stats'] as $s => $v) {
                    $roundStats[$rnd][$s] += $v;
                }
            }
        }

        // Compute averages per round
        $roundAverages = [];
        ksort($roundStats);
        foreach ($roundStats as $rnd => $data) {
            $count = $data['count'];
            $roundAverages[] = [
                'round' => $rnd,
                'games_alive' => $count,
                'success_rate' => round($data['successes'] / $count * 100, 1),
                'avg_wealth' => round($data['wealth'] / $count, 1),
                'avg_influence' => round($data['influence'] / $count, 1),
                'avg_security' => round($data['security'] / $count, 1),
                'avg_religion' => round($data['religion'] / $count, 1),
                'avg_food' => round($data['food'] / $count, 1),
                'avg_happiness' => round($data['happiness'] / $count, 1),
            ];
        }

        // Sort collapse reasons by frequency
        arsort($collapseReasons);

        ksort($scoreDistribution);
        ksort($roundDistribution);

        return [
            'summary' => [
                'total_games' => $numGames,
                'wins' => $wins,
                'losses' => $numGames - $wins,
                'win_rate' => round($wins / $numGames * 100, 1),
                'avg_score' => round($totalScore / $numGames, 1),
                'avg_rounds_survived' => round($totalRounds / $numGames, 1),
                'avg_final_stats' => array_map(fn ($v) => round($v / $numGames, 1), $statTotals),
            ],
            'collapse_reasons' => $collapseReasons,
            'score_distribution' => $scoreDistribution,
            'round_distribution' => $roundDistribution,
            'round_averages' => $roundAverages,
        ];
    }
}
