<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuelChoiceMade implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameId,
        public int $chooserPlayerNumber,
        public ?array $chooserCard,
        public ?array $offererCard,
        public string $duelPhase,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('game.' . $this->gameId)];
    }

    public function broadcastWith(): array
    {
        return [
            'chooser_player_number' => $this->chooserPlayerNumber,
            'chooser_card' => $this->chooserCard,
            'offerer_card' => $this->offererCard,
            'duel_phase' => $this->duelPhase,
        ];
    }
}
