<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Http\Controllers\MatchmakingController;
use App\Models\MatchmakingEntry;
use Illuminate\Console\Command;

/**
 * Server-side matchmaking so correspondence ("daily") players can queue, leave, and be
 * matched + notified while away — the poll-driven path only runs while a client is open.
 */
class ProcessMatchmakingQueue extends Command
{
    protected $signature = 'app:process-matchmaking';

    protected $description = 'Pair waiting duel matchmaking entries and bot-fill long-waiting correspondence queues';

    // A lone correspondence player is bot-matched after this long with no human opponent.
    private const DAILY_BOT_TIMEOUT = 180;

    public function handle(MatchmakingController $matchmaking): void
    {
        $matched = 0;

        MatchmakingEntry::where('status', 'searching')
            ->orderBy('created_at')
            ->get()
            ->each(function (MatchmakingEntry $entry) use ($matchmaking, &$matched): void {
                $entry->refresh();
                if ($entry->status !== 'searching') {
                    return; // paired earlier in this same run
                }

                // Widen the ELO window with age, mirroring the client-poll behaviour.
                $elapsed = (int) $entry->created_at->diffInSeconds(now());
                $range = min(500, 100 + (int) floor($elapsed / 5) * 100);
                if ($range !== $entry->elo_range) {
                    $entry->update(['elo_range' => $range]);
                }

                $opponent = $matchmaking->findMatch($entry);
                if ($opponent !== null) {
                    $matchmaking->createMatch($entry, $opponent);
                    $matched++;

                    return;
                }

                $threshold = $entry->speed_mode === 'daily' ? self::DAILY_BOT_TIMEOUT : ($entry->bot_timeout ?? 55);
                if ($elapsed >= $threshold) {
                    $matchmaking->createBotMatch($entry);
                    $matched++;
                }
            });

        $this->info("Processed matchmaking queue — {$matched} match(es) made.");
    }
}
