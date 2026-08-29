<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DailyChallenge;
use App\Services\ChallengeSimulator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SimulateDailyChallengeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public DailyChallenge $challenge,
        public int $runs = ChallengeSimulator::DEFAULT_RUNS,
    ) {}

    public function handle(ChallengeSimulator $simulator): void
    {
        $simulator->simulate($this->challenge, $this->runs);
    }
}
