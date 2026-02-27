<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GameInviteReceived implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $receiverId,
        public int $inviteId,
        public int $gameId,
        public string $senderName,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->receiverId)];
    }

    public function broadcastWith(): array
    {
        return [
            'invite_id' => $this->inviteId,
            'game_id' => $this->gameId,
            'sender_name' => $this->senderName,
        ];
    }
}
