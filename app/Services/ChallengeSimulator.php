<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Controllers\GameController;
use App\Models\DailyChallenge;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Estimates how hard a daily challenge is by playing it many times with a bot that makes
 * reasonable (weighted-random) decisions. Every run faces the identical seeded scenario a
 * human does — same character, starting stats, house rules, content pools, loadout, deck
 * order, dice and events — so the only thing that varies run to run is the decisions. The
 * resulting win rate answers "how forgiving is this puzzle for a competent player", and the
 * average months-to-win says how long a winning run tends to take.
 *
 * Each run is played through the real game engine (GameController) inside a transaction that
 * is rolled back, so simulating never leaves throwaway games behind.
 */
final class ChallengeSimulator
{
    public const DEFAULT_RUNS = 150;

    /**
     * The minimum bot win rate (percent) for a daily challenge to be considered winnable. A
     * competent human beats the bot, so a challenge the bot clears ~40% of the time leaves
     * comfortable headroom; anything lower is treated as too hard and eased by the balancer.
     */
    public const MIN_WINNABLE_RATE = 40;

    public function __construct(
        private readonly GameController $gameController,
        private readonly BotService $botService,
    ) {}

    /**
     * Simulate the challenge and persist the aggregate result onto it.
     *
     * @return array{runs: int, wins: int, success_rate: int, avg_months: float|null}
     */
    public function simulate(DailyChallenge $challenge, int $runs = self::DEFAULT_RUNS): array
    {
        $runs = max(1, $runs);
        $wins = 0;

        /** @var list<int> $roundsToWin */
        $roundsToWin = [];

        // Everything below (the throwaway sim user and every simulated game) is written inside
        // this transaction and rolled back once we have the tallies, leaving no residue.
        try {
            DB::transaction(function () use (&$wins, &$roundsToWin, $challenge, $runs): void {
                $simUser = $this->createSimulationUser();

                for ($runIndex = 0; $runIndex < $runs; $runIndex++) {
                    $outcome = $this->playSingleRun($challenge, $simUser, $runIndex);

                    if ($outcome['result'] === 'win') {
                        $wins++;
                        $roundsToWin[] = $outcome['rounds'];
                    }
                }

                throw new SimulationComplete;
            });
        } catch (SimulationComplete) {
            // Expected — forces the rollback of the whole simulation batch.
        }

        $successRate = (int) round(100 * $wins / $runs);
        $averageMonths = $roundsToWin === []
            ? null
            : round(array_sum($roundsToWin) / count($roundsToWin), 1);

        $challenge->update([
            'sim_runs' => $runs,
            'sim_success_rate' => $successRate,
            'sim_avg_months' => $averageMonths,
            'sim_computed_at' => now(),
        ]);

        return [
            'runs' => $runs,
            'wins' => $wins,
            'success_rate' => $successRate,
            'avg_months' => $averageMonths,
        ];
    }

    /**
     * Play one full run in its own rolled-back savepoint and return only its outcome.
     *
     * @return array{result: 'win'|'loss', rounds: int}
     */
    private function playSingleRun(DailyChallenge $challenge, User $simUser, int $runIndex): array
    {
        // Seed the bot's decisions per run so a given run is reproducible, while different
        // runs explore different decision paths. mt_rand is isolated from the CSPRNG the game
        // engine uses elsewhere, so this only steers the bot's own choices.
        mt_srand(crc32("challenge-sim:{$challenge->id}:{$runIndex}"));
        $random = static fn (): float => mt_rand() / (mt_getrandmax() + 1);

        $outcome = ['result' => 'loss', 'rounds' => 0];

        try {
            DB::transaction(function () use (&$outcome, $challenge, $simUser, $random): void {
                $game = $this->gameController->setupDailyGame($challenge, $simUser);
                $outcome = $this->gameController->simulateDailyRun($game, $this->botService, $random);

                throw new SimulationComplete;
            });
        } catch (SimulationComplete) {
            // Expected — rolls back this run's throwaway game, keeping only $outcome.
        }

        return $outcome;
    }

    private function createSimulationUser(): User
    {
        return User::factory()->create([
            'name' => 'Challenge Simulator',
            'email' => 'challenge-simulator+'.uniqid().'@internal.invalid',
        ]);
    }
}
