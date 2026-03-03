<?php

namespace App\Console\Commands;

use App\Http\Controllers\GameController;
use App\Models\Game;
use App\Models\GamePlayerHand;
use App\Models\GameRoundResult;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class ProcessExpiredTurns extends Command
{
    protected $signature = 'app:process-expired-turns';

    protected $description = 'Auto-advance online duel games with expired turn timers';

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
                $this->autoAdvance($game);
            } catch (\Throwable $e) {
                $this->error("Failed to auto-advance game {$game->id}: {$e->getMessage()}");
            }
        }

        if ($expiredGames->count()) {
            $this->info("Processed {$expiredGames->count()} expired turn(s).");
        }
    }

    private function autoAdvance(Game $game): void
    {
        $controller = app(GameController::class);
        $phase = $game->duel_phase;

        if ($phase === 'choosing') {
            $this->autoAdvanceChoosing($game, $controller);
        } elseif (in_array($phase, ['rolling', 'rolling_offerer', 'rolling_chooser'])) {
            $this->autoAdvanceRolling($game, $controller);
        }
    }

    private function autoAdvanceChoosing(Game $game, GameController $controller): void
    {
        $players = $game->players()->orderBy('player_number')->get();

        foreach ($players as $player) {
            $hands = GamePlayerHand::where('game_id', $game->id)
                ->where('game_player_id', $player->id)
                ->where('round_number', $game->current_round)
                ->get();

            $alreadySubmitted = $hands->whereNotNull('offered_to_player_id')->isNotEmpty();
            if ($alreadySubmitted) {
                continue;
            }

            // Pick a random card to keep
            $keptHand = $hands->random();
            $user = $player->user;
            if (!$user) continue;

            $fakeRequest = Request::create('', 'POST', ['kept_hand_id' => $keptHand->id]);
            $fakeRequest->setUserResolver(fn () => $user);
            $fakeRequest->setLaravelSession(app('session.store'));

            $controller->duelSelect($game->fresh(), $fakeRequest);
            $game->refresh();
        }
    }

    private function autoAdvanceRolling(Game $game, GameController $controller): void
    {
        $players = $game->players()->orderBy('player_number')->get();

        foreach ($players as $player) {
            $alreadyRolled = GameRoundResult::where('game_id', $game->id)
                ->where('round_number', $game->current_round)
                ->where('game_player_id', $player->id)
                ->exists();

            if ($alreadyRolled) continue;

            // Check if this player should roll in current phase
            $shouldRoll = false;
            $phase = $game->duel_phase;

            if ($phase === 'rolling') {
                $shouldRoll = true;
            } elseif ($phase === 'rolling_offerer' && $game->offerer_player_number === $player->player_number) {
                $shouldRoll = true;
            } elseif ($phase === 'rolling_chooser') {
                $chooserNum = $game->offerer_player_number === 1 ? 2 : 1;
                $shouldRoll = $player->player_number === $chooserNum;
            }

            if (!$shouldRoll) continue;

            $user = $player->user;
            if (!$user) continue;

            $fakeRequest = Request::create('', 'POST', ['player_number' => $player->player_number]);
            $fakeRequest->setUserResolver(fn () => $user);
            $fakeRequest->setLaravelSession(app('session.store'));

            $controller->duelRoll($game->fresh(), $fakeRequest);
            $game->refresh();
        }
    }
}
