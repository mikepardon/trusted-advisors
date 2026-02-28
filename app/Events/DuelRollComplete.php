<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DuelRollComplete implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $gameId,
        public int $playerNumber,
        public array $rollData,
        public string $duelPhase,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('game.' . $this->gameId)];
    }

    public function broadcastWith(): array
    {
        return [
            'player_number' => $this->playerNumber,
            'roll_data' => $this->rollData,
            'duel_phase' => $this->duelPhase,
        ];
    }
}
