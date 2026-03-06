<?php

namespace App\Console\Commands;

use App\Jobs\SendAdminGiftToUsers;
use App\Models\AdminGift;
use Illuminate\Console\Command;

class ProcessScheduledGifts extends Command
{
    protected $signature = 'app:process-scheduled-gifts';
    protected $description = 'Process scheduled admin gifts that are due';

    public function handle(): int
    {
        $gifts = AdminGift::where('status', 'scheduled')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($gifts->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($gifts as $gift) {
            $gift->update(['status' => 'sending']);

            $validated = [
                'reward_xp' => $gift->reward_xp ?? 0,
                'reward_coins' => $gift->reward_coins ?? 0,
                'reward_character_id' => $gift->reward_character_id,
                'reward_dice_theme_id' => $gift->reward_dice_theme_id,
                'reward_kingdom_style_id' => $gift->reward_kingdom_style_id,
            ];

            SendAdminGiftToUsers::dispatchSync($gift, $validated);

            $gift->update(['status' => 'sent']);

            $this->info("Processed gift #{$gift->id}: {$gift->title}");
        }

        $this->info("Processed {$gifts->count()} scheduled gift(s).");

        return self::SUCCESS;
    }
}
