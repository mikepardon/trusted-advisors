<?php

namespace App\Services;

use App\Events\DuelGameOver;
use App\Models\Game;
use App\Models\GamePlayerHand;
use App\Models\GameRoundResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DuelForfeitService
{
    /**
     * Check if the game's turn timer has expired and forfeit if so.
     * Idempotent: re-checks status === 'active' inside a transaction.
     *
     * @return bool True if a forfeit/draw was processed, false if no-op.
     */
    public function handleTimeoutIfExpired(Game $game): bool
    {
        return DB::transaction(function () use ($game) {
            // Re-load with a lock to prevent races
            $game = Game::lockForUpdate()->find($game->id);

            if (!$game || $game->status !== 'active') {
                return false;
            }

            if (!$game->turn_time_limit || !$game->turn_started_at) {
                return false;
            }

            $remaining = $game->turnTimeRemaining();
            if ($remaining > 0) {
                return false;
            }

            $timedOutPlayerNumbers = $this->determineTimedOutPlayers($game);

            if (empty($timedOutPlayerNumbers)) {
                return false;
            }

            $players = $game->players()->with('user')->orderBy('player_number')->get();

            if (count($timedOutPlayerNumbers) >= 2) {
                $this->endGameAsDraw($game, $players);
            } else {
                $timedOutNumber = $timedOutPlayerNumbers[0];
                $winnerNumber = $timedOutNumber === 1 ? 2 : 1;
                $this->endGameAsForfeit($game, $players, $winnerNumber, $timedOutNumber);
            }

            return true;
        });
    }

    /**
     * Determine which players have timed out based on the current duel phase.
     */
    public function determineTimedOutPlayers(Game $game): array
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

        Log::info("Game {$game->id}: Player {$timedOutNumber} timed out. Player {$winnerNumber} wins.");
    }

    private function endGameAsDraw(Game $game, $players): void
    {
        $game->update([
            'status' => 'completed',
            'round_phase' => 'complete',
            'winner_player_number' => null,
            'timed_out_player_number' => 0,
            'turn_started_at' => null,
        ]);

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

        Log::info("Game {$game->id}: Both players timed out. Draw.");
    }
}
