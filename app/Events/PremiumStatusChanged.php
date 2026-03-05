<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PremiumStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public bool $isPremium,
        public ?string $platform,
        public ?string $expiresAt,
        public ?string $purchaseType,
        public string $status, // 'activated' | 'purchase_completed' | 'cancelled'
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('user.' . $this->userId)];
    }

    public function broadcastWith(): array
    {
        return [
            'is_premium' => $this->isPremium,
            'platform' => $this->platform,
            'expires_at' => $this->expiresAt,
            'purchase_type' => $this->purchaseType,
            'status' => $this->status,
        ];
    }
}
