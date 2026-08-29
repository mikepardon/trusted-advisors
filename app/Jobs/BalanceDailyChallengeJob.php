<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DailyChallenge;
use App\Services\ChallengeBalancer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Tunes a daily challenge in the background until it is winnable. Used by the HTTP range
 * generator, which creates challenges quickly (unverified) and hands verification to the queue.
 */
class BalanceDailyChallengeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public DailyChallenge $challenge,
    ) {}

    public function handle(ChallengeBalancer $balancer): void
    {
        $balancer->balance($this->challenge);
    }
}
