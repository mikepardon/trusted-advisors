<?php

namespace App\Console\Commands;

use App\Models\Season;
use App\Services\SeasonRewardService;
use Illuminate\Console\Command;

class ProcessSeasonEnd extends Command
{
    protected $signature = 'app:process-season-end {--season= : Specific season ID to process}';

    protected $description = 'Process ended seasons and distribute rewards';

    public function handle(SeasonRewardService $service): void
    {
        if ($seasonId = $this->option('season')) {
            $season = Season::findOrFail($seasonId);
            $result = $service->processSeasonEnd($season);
            $this->info("Processed season \"{$season->name}\": {$result['rewards_distributed']} rewards distributed.");
            return;
        }

        // Find active seasons that have ended
        $seasons = Season::where('is_active', true)
            ->where('ends_at', '<=', now())
            ->get();

        if ($seasons->isEmpty()) {
            $this->info('No seasons to process.');
            return;
        }

        foreach ($seasons as $season) {
            $result = $service->processSeasonEnd($season);
            $this->info("Processed season \"{$season->name}\": {$result['rewards_distributed']} rewards distributed.");
        }
    }
}
