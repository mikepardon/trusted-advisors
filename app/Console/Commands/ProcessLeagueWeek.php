<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\LeagueCohort;
use App\Models\LeagueMember;
use App\Models\LeagueResult;
use App\Services\LeagueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessLeagueWeek extends Command
{
    protected $signature = 'app:process-league-week {--week= : The Monday (Y-m-d) of the week to finalise; defaults to last week}';

    protected $description = 'Finalise a league week: promote/demote players and award placement rewards.';

    /** Coins for the top three real players in each cohort. */
    private const PLACEMENT_COINS = [500, 300, 200];

    public function __construct(private readonly LeagueService $league)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $week = $this->option('week')
            ?? $this->league->currentWeekStart()->subWeek()->toDateString();

        $maxTier = count(LeagueService::TIERS) - 1;
        $cohorts = LeagueCohort::query()->whereDate('week_start', $week)->get();

        foreach ($cohorts as $cohort) {
            $this->processCohort($cohort, $maxTier);
        }

        $this->info("Processed {$cohorts->count()} cohort(s) for week {$week}.");

        return self::SUCCESS;
    }

    private function processCohort(LeagueCohort $cohort, int $maxTier): void
    {
        DB::transaction(function () use ($cohort, $maxTier): void {
            // Lock the cohort and re-read its processed marker inside the transaction
            // so a re-run (manual retry, scheduler double-fire) finalises each cohort
            // exactly once instead of awarding placement coins and promotions twice.
            $locked = LeagueCohort::query()->whereKey($cohort->id)->lockForUpdate()->first();

            if ($locked->processed_at !== null) {
                return;
            }

            $members = LeagueMember::query()
                ->where('cohort_id', $cohort->id)
                ->with('user')
                ->orderByDesc('score')
                ->orderBy('id')
                ->get();

            $total = $members->count();

            foreach ($members->values() as $index => $member) {
                // A null user_id is a bot placeholder — expected, and not eligible for
                // promotion or rewards. A set user_id with a missing user is an orphaned
                // membership worth surfacing.
                if ($member->user_id === null) {
                    continue;
                }

                if ($member->user === null) {
                    Log::warning('League member references a missing user', [
                        'cohort_id' => $cohort->id,
                        'member_id' => $member->id,
                    ]);

                    continue;
                }

                $rank = $index + 1;
                $user = $member->user;
                $tierBefore = (int) $user->league_tier;

                if ($rank <= LeagueService::PROMOTE_COUNT && $user->league_tier < $maxTier) {
                    $user->league_tier++;
                } elseif ($rank > $total - LeagueService::DEMOTE_COUNT && $user->league_tier > 0) {
                    $user->league_tier--;
                }

                $coins = 0;
                if ($rank <= count(self::PLACEMENT_COINS)) {
                    $coins = self::PLACEMENT_COINS[$rank - 1];
                    $user->coins += $coins;
                    $user->recordCoinTransaction($coins, 'earn', 'league_placement', null, "League — rank {$rank}");
                }

                $user->save();

                // Snapshot the finalised standing so the player sees an end-of-week overview
                // on next login. Written under the same lock/processed_at guard, so it can't
                // duplicate on a re-run.
                LeagueResult::create([
                    'user_id' => $user->id,
                    'cohort_id' => $cohort->id,
                    'week_start' => $cohort->week_start,
                    'tier' => $cohort->tier,
                    'rank' => $rank,
                    'total' => $total,
                    'tier_before' => $tierBefore,
                    'tier_after' => (int) $user->league_tier,
                    'coins_earned' => $coins,
                ]);
            }

            $locked->processed_at = now();
            $locked->save();
        });
    }
}
