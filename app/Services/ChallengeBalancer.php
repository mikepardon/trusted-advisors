<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyChallenge;

/**
 * Guarantees a daily challenge is actually winnable. It simulates the challenge and, while the
 * bot win rate sits below {@see ChallengeSimulator::MIN_WINNABLE_RATE}, applies a fixed ladder
 * of easing steps — softening the environment first, only loosening the goal itself later — and
 * re-simulates until the challenge is beatable. The final (winnable) criteria and cached sim
 * result are persisted onto the challenge.
 *
 * The ladder is deterministic and the dice/deck are seeded, so a given challenge always tunes to
 * the same place: an impossible "raise every stat to 13" becomes a demanding-but-beatable run
 * rather than a wall no one can clear.
 */
final class ChallengeBalancer
{
    /** Runs per simulation while tuning — fewer than a full report, enough to spot "impossible". */
    public const TUNING_RUNS = 24;

    /** Ceiling on easing attempts before falling back to a guaranteed-easy configuration. */
    public const MAX_STEPS = 8;

    /**
     * Normalise any run to at most this many months. An endless-race run should resolve (goal or
     * collapse) well within this; capping it keeps each simulated game — and so the whole tuning
     * pass — cheap, especially once "no negative effects" removes the collapse path.
     */
    public const ROUND_CAP = 40;

    private const STATS = ['wealth', 'influence', 'security', 'religion', 'food', 'happiness'];

    public function __construct(private readonly ChallengeSimulator $simulator) {}

    /**
     * Tune the challenge until the bot can win it, persisting the result. Returns a summary.
     *
     * @return array{success_rate: int, avg_months: float|null, steps: int, winnable: bool}
     */
    public function balance(DailyChallenge $challenge, int $runs = self::TUNING_RUNS): array
    {
        $this->capRounds($challenge);

        $result = $this->simulator->simulate($challenge, $runs);
        $steps = 0;

        while ($result['success_rate'] < ChallengeSimulator::MIN_WINNABLE_RATE && $steps < self::MAX_STEPS) {
            $challenge->update(['criteria' => $this->ease($challenge->criteria ?? [], $steps)]);
            $result = $this->simulator->simulate($challenge, $runs);
            $steps++;
        }

        return [
            'success_rate' => $result['success_rate'],
            'avg_months' => $result['avg_months'],
            'steps' => $steps,
            'winnable' => $result['success_rate'] >= ChallengeSimulator::MIN_WINNABLE_RATE,
        ];
    }

    /**
     * Pull an over-long round cap down to {@see self::ROUND_CAP} so simulating stays cheap and a
     * run can't "win" merely by limping through dozens of extra months. A no-op when already low.
     */
    private function capRounds(DailyChallenge $challenge): void
    {
        $criteria = $challenge->criteria ?? [];
        $rounds = (int) ($criteria['rounds'] ?? self::ROUND_CAP);

        if ($rounds > self::ROUND_CAP) {
            $criteria['rounds'] = self::ROUND_CAP;
            $challenge->update(['criteria' => $criteria]);
        }
    }

    /**
     * The easing ladder: gentler steps (soften the environment) come first so a challenge keeps
     * its intended goal for as long as possible; the goal only loosens once the environment is
     * already forgiving.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function ease(array $criteria, int $step): array
    {
        return match ($step) {
            0 => $this->dropPunishingHouseRules($criteria),
            1 => $this->raiseStartingStats($criteria, 9, 7),
            2 => $this->setHouseRule($criteria, 'no_negative_effects'),
            3 => $this->loosenGoal($criteria, 2),
            4 => $this->setHouseRule($this->raiseStartingStats($criteria, 11, 9), 'double_positive_effects'),
            5 => $this->loosenGoal($criteria, 2),
            6 => $this->loosenGoal($this->raiseStartingStats($criteria, 12, 10), 2),
            default => $this->guaranteedEasy($criteria),
        };
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function dropPunishingHouseRules(array $criteria): array
    {
        foreach (['hardcore_mode', 'draw_curse_per_round'] as $rule) {
            unset($criteria['house_rules'][$rule]);
        }

        return $criteria;
    }

    /**
     * Lift the uniform start to at least $allMin and pull any deliberately-weak stat up to at
     * least $weakMin, so the player is not starting in a hole they cannot climb out of.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function raiseStartingStats(array $criteria, int $allMin, int $weakMin): array
    {
        $criteria['start']['all'] = max((int) ($criteria['start']['all'] ?? 8), $allMin);

        if (! empty($criteria['start']['per_stat']) && is_array($criteria['start']['per_stat'])) {
            foreach ($criteria['start']['per_stat'] as $stat => $value) {
                $criteria['start']['per_stat'][$stat] = max((int) $value, $weakMin);
            }
        }

        return $criteria;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function setHouseRule(array $criteria, string $rule): array
    {
        $criteria['house_rules'][$rule] = true;

        return $criteria;
    }

    /**
     * Lower whatever the goal demands by $delta, floored so the goal stays meaningful.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function loosenGoal(array $criteria, int $delta): array
    {
        $goal = $criteria['goal'] ?? [];

        $criteria['goal'] = match ($goal['type'] ?? null) {
            'stat_threshold' => [...$goal, 'value' => max(12, (int) ($goal['value'] ?? 14) - $delta)],
            'no_stat_below' => [...$goal, 'value' => max(7, (int) ($goal['value'] ?? 9) - $delta)],
            'stat_threshold_all' => [
                ...$goal,
                'targets' => array_map(
                    fn ($target): int => max(11, (int) $target - $delta),
                    $goal['targets'] ?? [],
                ),
            ],
            default => $goal,
        };

        return $criteria;
    }

    /**
     * Last resort: a configuration that is winnable by construction — a forgiving environment,
     * healthy starting stats, and a modest goal.
     *
     * @param  array<string, mixed>  $criteria
     * @return array<string, mixed>
     */
    private function guaranteedEasy(array $criteria): array
    {
        $criteria['house_rules'] = ['no_negative_effects' => true, 'double_positive_effects' => true];
        $criteria['start']['all'] = 12;
        $criteria['start']['per_stat'] = [];

        $goal = $criteria['goal'] ?? [];
        $criteria['goal'] = match ($goal['type'] ?? null) {
            'no_stat_below' => ['type' => 'no_stat_below', 'value' => 10],
            'stat_threshold_all' => [
                'type' => 'stat_threshold_all',
                'targets' => collect(self::STATS)->mapWithKeys(fn (string $stat): array => [$stat => 13])->all(),
            ],
            default => ['type' => 'stat_threshold', 'stat' => $goal['stat'] ?? 'wealth', 'value' => 14],
        };

        return $criteria;
    }
}
