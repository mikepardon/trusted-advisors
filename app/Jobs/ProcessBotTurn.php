<?php

namespace App\Jobs;

use App\Http\Controllers\GameController;
use App\Models\Game;
use App\Models\GamePlayerHand;
use App\Models\GameRoundResult;
use App\Models\User;
use App\Services\BotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBotTurn implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 15;

    public function __construct(
        public int $gameId,
        public string $expectedTurnStartedAt,
    ) {}

    public function handle(): void
    {
        $game = Game::find($this->gameId);

        if (!$game || $game->status !== 'active') {
            return;
        }

        // Idempotency: if turn_started_at changed, the phase advanced and this job is stale
        if (!$game->turn_started_at || $game->turn_started_at->toIso8601String() !== $this->expectedTurnStartedAt) {
            return;
        }

        $bot = $game->players()->where('is_bot', true)->first();
        if (!$bot) {
            return;
        }

        $botUser = User::find($bot->user_id);
        if (!$botUser) {
            return;
        }

        $phase = $game->duel_phase;
        $controller = app(GameController::class);

        if ($phase === 'choosing') {
            // Check if bot has already submitted
            $hasSubmitted = GamePlayerHand::where('game_id', $game->id)
                ->where('game_player_id', $bot->id)
                ->where('round_number', $game->current_round)
                ->whereNotNull('offered_to_player_id')
                ->exists();

            if ($hasSubmitted) {
                return;
            }

            $botService = app(BotService::class);
            $handId = $botService->decideDuelSelect($game, $bot);

            $fakeRequest = Request::create('', 'POST', ['kept_hand_id' => $handId]);
            $fakeRequest->setUserResolver(fn () => $botUser);
            $controller->duelSelect($game->fresh(), $fakeRequest);

            Log::debug("ProcessBotTurn: Bot selected card for game {$this->gameId}");
        } elseif (in_array($phase, ['rolling', 'rolling_offerer', 'rolling_chooser'])) {
            $shouldRoll = false;

            if ($phase === 'rolling') {
                $alreadyRolled = GameRoundResult::where('game_id', $game->id)
                    ->where('round_number', $game->current_round)
                    ->where('game_player_id', $bot->id)
                    ->exists();
                $shouldRoll = !$alreadyRolled;
            } elseif ($phase === 'rolling_offerer') {
                $shouldRoll = $game->offerer_player_number === $bot->player_number;
            } else {
                $chooserNumber = $game->offerer_player_number === 1 ? 2 : 1;
                $shouldRoll = $chooserNumber === $bot->player_number;
            }

            if ($shouldRoll) {
                $fakeRequest = Request::create('', 'POST', ['player_number' => $bot->player_number]);
                $fakeRequest->setUserResolver(fn () => $botUser);
                $controller->duelRoll($game->fresh(), $fakeRequest);

                Log::debug("ProcessBotTurn: Bot rolled for game {$this->gameId} in phase {$phase}");
            }
        }
    }
}
