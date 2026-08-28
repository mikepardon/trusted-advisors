<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NextRoundStarted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Pusher rejects messages whose data exceeds 10KB. Stay under that with a
     * margin for the channel/event envelope Pusher wraps around the payload.
     */
    public const MAX_PAYLOAD_BYTES = 9216;

    public function __construct(
        public int $gameId,
        public array $gameData,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('game.' . $this->gameId)];
    }

    public function broadcastWith(): array
    {
        $encoded = json_encode($this->gameData);

        if ($encoded !== false && strlen($encoded) <= self::MAX_PAYLOAD_BYTES) {
            return $this->gameData;
        }

        // Full state (6 players with characters, items and curses) can outgrow the
        // Pusher limit. Send a marker instead so clients refetch over HTTP rather
        // than the whole broadcast being dropped.
        return [
            'refetch' => true,
            'current_round' => $this->gameData['current_round'] ?? null,
            'round_phase' => $this->gameData['round_phase'] ?? null,
        ];
    }
}
