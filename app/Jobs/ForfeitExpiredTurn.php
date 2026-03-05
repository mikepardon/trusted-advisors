<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\DuelForfeitService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ForfeitExpiredTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 15;

    public function __construct(
        public int $gameId,
        public string $expectedTurnStartedAt,
    ) {}

    public function handle(DuelForfeitService $forfeitService): void
    {
        $game = Game::find($this->gameId);

        if (!$game || $game->status !== 'active') {
            return;
        }

        // Idempotency: if turn_started_at changed, the phase advanced and this job is stale
        if (!$game->turn_started_at || $game->turn_started_at->toIso8601String() !== $this->expectedTurnStartedAt) {
            Log::debug("ForfeitExpiredTurn: stale job for game {$this->gameId}, turn_started_at changed.");
            return;
        }

        $forfeitService->handleTimeoutIfExpired($game);
    }
}
