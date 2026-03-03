<?php

namespace App\Console\Commands;

use App\Events\DuelGameOver;
use App\Models\Game;
use App\Models\GamePlayerHand;
use App\Models\GameRoundResult;
use App\Services\GameCompletionService;
use Illuminate\Console\Command;

class ProcessExpiredTurns extends Command
{
    protected $signature = 'app:process-expired-turns';

    protected $description = 'Forfeit online duel games with expired turn timers';

    public function handle(): void
    {
        $expiredGames = Game::where('status', 'active')
            ->where('game_mode', 'online')
            ->where('game_type', 'duel')
            ->whereNotNull('turn_time_limit')
            ->whereNotNull('turn_started_at')
            ->whereRaw("datetime(turn_started_at, '+' || turn_time_limit || ' seconds') <= datetime('now')")
            ->get();

        foreach ($expiredGames as $game) {
            try {
                $this->handleTimeout($game);
            } catch (\Throwable $e) {
                $this->error("Failed to process timeout for game {$game->id}: {$e->getMessage()}");
            }
        }

        if ($expiredGames->count()) {
            $this->info("Processed {$expiredGames->count()} expired turn(s).");
        }
    }

    private function handleTimeout(Game $game): void
    {
        $timedOutPlayerNumbers = $this->determineTimedOutPlayers($game);

        if (empty($timedOutPlayerNumbers)) {
            return;
        }

        $players = $game->players()->with('user')->orderBy('player_number')->get();

        if (count($timedOutPlayerNumbers) >= 2) {
            // Both players timed out — draw
            $this->endGameAsDraw($game, $players);
        } else {
            // One player timed out — opponent wins
            $timedOutNumber = $timedOutPlayerNumbers[0];
            $winnerNumber = $timedOutNumber === 1 ? 2 : 1;
            $this->endGameAsForfeit($game, $players, $winnerNumber, $timedOutNumber);
        }
    }

    private function determineTimedOutPlayers(Game $game): array
    {
        $phase = $game->duel_phase;
        $timedOut = [];

        if ($phase === 'choosing') {
            $players = $game->players()->orderBy('player_number')->get();
            foreach ($players as $player) {
                $hasSubmitted = GamePlayerHand::where('game_id', $game->id)
                    ->where('game_player_id', $player->id)
                    ->where('round_number', $game->current_round)
                    ->whereNotNull('offered_to_player_id')
                    ->exists();

                if (!$hasSubmitted) {
                    $timedOut[] = $player->player_number;
                }
            }
        } elseif ($phase === 'rolling') {
            // Simultaneous rolling — check who hasn't rolled
            $players = $game->players()->orderBy('player_number')->get();
            foreach ($players as $player) {
                $hasRolled = GameRoundResult::where('game_id', $game->id)
                    ->where('round_number', $game->current_round)
                    ->where('game_player_id', $player->id)
                    ->exists();

                if (!$hasRolled) {
                    $timedOut[] = $player->player_number;
                }
            }
        } elseif ($phase === 'rolling_offerer') {
            $timedOut[] = $game->offerer_player_number;
        } elseif ($phase === 'rolling_chooser') {
            $chooserNumber = $game->offerer_player_number === 1 ? 2 : 1;
            $timedOut[] = $chooserNumber;
        }

        return $timedOut;
    }

    private function endGameAsForfeit(Game $game, $players, int $winnerNumber, int $timedOutNumber): void
    {
        $game->update([
            'status' => 'completed',
            'round_phase' => 'complete',
            'winner_player_number' => $winnerNumber,
            'timed_out_player_number' => $timedOutNumber,
            'turn_started_at' => null,
        ]);

        // Increment timeout count for the timed-out user
        $timedOutPlayer = $players->firstWhere('player_number', $timedOutNumber);
        if ($timedOutPlayer?->user) {
            $timedOutPlayer->user->increment('timeout_count');
        }

        $completionSummary = app(GameCompletionService::class)->processCompletion($game);
        $kingdoms = $game->playerKingdoms()->with('player')->get();

        $gameOverData = [
            'game_over' => true,
            'winner_player_number' => $winnerNumber,
            'timed_out_player_number' => $timedOutNumber,
            'reason' => 'Player ' . $timedOutNumber . ' ran out of time. Player ' . $winnerNumber . ' wins by forfeit!',
            'game' => $game->fresh(),
            'player_kingdoms' => $kingdoms,
            'completion' => $completionSummary,
        ];

        broadcast(new DuelGameOver($game->id, $gameOverData));

        $this->info("Game {$game->id}: Player {$timedOutNumber} timed out. Player {$winnerNumber} wins.");
    }

    private function endGameAsDraw(Game $game, $players): void
    {
        $game->update([
            'status' => 'completed',
            'round_phase' => 'complete',
            'winner_player_number' => null,
            'timed_out_player_number' => 0, // 0 = both timed out
            'turn_started_at' => null,
        ]);

        // Increment timeout count for both users
        foreach ($players as $player) {
            if ($player->user) {
                $player->user->increment('timeout_count');
            }
        }

        $completionSummary = app(GameCompletionService::class)->processCompletion($game);
        $kingdoms = $game->playerKingdoms()->with('player')->get();

        $gameOverData = [
            'game_over' => true,
            'winner_player_number' => null,
            'timed_out_player_number' => 0,
            'reason' => 'Both players ran out of time. The game ends in a draw!',
            'game' => $game->fresh(),
            'player_kingdoms' => $kingdoms,
            'completion' => $completionSummary,
        ];

        broadcast(new DuelGameOver($game->id, $gameOverData));

        $this->info("Game {$game->id}: Both players timed out. Draw.");
    }
}
