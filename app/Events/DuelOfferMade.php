<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuelOfferMade implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameId,
        public int $offererPlayerNumber,
        public int $revealedCardId,
        public string $duelPhase,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('game.' . $this->gameId)];
    }

    public function broadcastWith(): array
    {
        return [
            'offerer_player_number' => $this->offererPlayerNumber,
            'revealed_card_id' => $this->revealedCardId,
            'duel_phase' => $this->duelPhase,
        ];
    }
}
