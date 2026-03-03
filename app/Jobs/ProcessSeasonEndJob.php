<?php

namespace App\Jobs;

use App\Models\Season;
use App\Services\SeasonRewardService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessSeasonEndJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public Season $season,
    ) {}

    public function handle(SeasonRewardService $service): void
    {
        $service->processSeasonEnd($this->season);
    }
}
