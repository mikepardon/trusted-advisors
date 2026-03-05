<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\DuelForfeitService;
use Illuminate\Console\Command;

class ProcessExpiredTurns extends Command
{
    protected $signature = 'app:process-expired-turns';

    protected $description = 'Forfeit online duel games with expired turn timers';

    public function handle(DuelForfeitService $forfeitService): void
    {
        $expiredGames = Game::where('status', 'active')
            ->where('game_mode', 'online')
            ->where('game_type', 'duel')
            ->whereNotNull('turn_time_limit')
            ->whereNotNull('turn_started_at')
            ->whereRaw("datetime(turn_started_at, '+' || turn_time_limit || ' seconds') <= datetime('now')")
            ->get();

        $processed = 0;

        foreach ($expiredGames as $game) {
            try {
                if ($forfeitService->handleTimeoutIfExpired($game)) {
                    $processed++;
                }
            } catch (\Throwable $e) {
                $this->error("Failed to process timeout for game {$game->id}: {$e->getMessage()}");
            }
        }

        if ($processed) {
            $this->info("Processed {$processed} expired turn(s).");
        }
    }
}
