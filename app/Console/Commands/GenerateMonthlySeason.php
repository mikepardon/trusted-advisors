<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Season;
use App\Services\SeasonPassService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateMonthlySeason extends Command
{
    protected $signature = 'app:generate-monthly-season';

    protected $description = 'Open a new month-long season (with its pass tiers) when none is active.';

    public function handle(SeasonPassService $seasonPass): int
    {
        // Idempotent: if a season already covers today, there is nothing to open. Runs
        // daily after ProcessSeasonEnd, so on the 1st the expired season is already closed.
        if ($seasonPass->activeSeason() !== null) {
            $this->info('A season already covers the current window; nothing to open.');

            return self::SUCCESS;
        }

        $now = CarbonImmutable::now();

        $season = DB::transaction(function () use ($seasonPass, $now): Season {
            $season = Season::create([
                'name' => $now->format('F Y'),
                'starts_at' => $now->startOfMonth(),
                'ends_at' => $now->endOfMonth(),
                'is_active' => true,
            ]);

            $seasonPass->seedTiers($season);

            return $season;
        });

        $this->info("Opened season \"{$season->name}\" with its pass tiers.");

        return self::SUCCESS;
    }
}
