<?php

namespace App\Jobs;

use App\Events\UserNotificationReceived;
use App\Models\AdminGift;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\GiftTargetingService;
use App\Services\OneSignalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAdminGiftToUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        public AdminGift $gift,
        public array $validated,
    ) {}

    public function handle(OneSignalService $oneSignal, GiftTargetingService $targeting): void
    {
        $count = 0;
        $query = $targeting->buildQuery($this->gift);

        $query->chunkById(100, function ($users) use ($oneSignal, &$count) {
            foreach ($users as $user) {
                $notification = UserNotification::create([
                    'user_id' => $user->id,
                    'type' => 'admin_gift',
                    'title' => $this->gift->title,
                    'message' => $this->gift->note ?? 'You have received a gift from the realm!',
                    'data' => [
                        'admin_gift_id' => $this->gift->id,
                        'reward_xp' => $this->validated['reward_xp'] ?? 0,
                        'reward_coins' => $this->validated['reward_coins'] ?? 0,
                        'reward_character_id' => $this->validated['reward_character_id'] ?? null,
                        'reward_dice_theme_id' => $this->validated['reward_dice_theme_id'] ?? null,
                        'reward_kingdom_style_id' => $this->validated['reward_kingdom_style_id'] ?? null,
                    ],
                ]);

                try {
                    broadcast(new UserNotificationReceived(
                        $user->id,
                        $notification->id,
                        'admin_gift',
                        $this->gift->title,
                    ));
                } catch (\Throwable) {}

                try {
                    $oneSignal->sendToUser(
                        $user,
                        'Gift from the Realm!',
                        $this->gift->title,
                        ['type' => 'admin_gift', 'gift_id' => $this->gift->id],
                    );
                } catch (\Throwable) {
                    // Don't let push failures stop the loop
                }

                $count++;
            }
        });

        $this->gift->update(['recipient_count' => $count]);
    }
}
