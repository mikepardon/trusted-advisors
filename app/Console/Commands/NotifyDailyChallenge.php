<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\DailyChallenge;
use App\Models\User;
use App\Services\OneSignalService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyDailyChallenge extends Command
{
    protected $signature = 'app:notify-daily-challenge {--date= : Specific date (YYYY-MM-DD), defaults to today}';

    protected $description = "Push a 'today's challenge is live' notification to subscribed players";

    public function handle(OneSignalService $oneSignal): void
    {
        $date = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::today();
        $challenge = DailyChallenge::whereDate('date', $date->toDateString())->first();

        if (! $challenge) {
            $this->info("No challenge for {$date->toDateString()}.");

            return;
        }

        $sent = 0;
        // Only iterate players with a push token; notifyUser also honours each player's
        // per-category opt-out, so opted-out users are skipped without a wasted call.
        User::whereNotNull('onesignal_player_id')->chunkById(200, function ($users) use ($oneSignal, $challenge, &$sent): void {
            foreach ($users as $user) {
                $oneSignal->notifyUser(
                    $user,
                    'challenge',
                    "Today's Trial is live",
                    "{$challenge->title} — race to the target and top the leaderboard.",
                    ['type' => 'daily_live', 'challenge_id' => $challenge->id],
                );
                $sent++;
            }
        });

        $this->info("Notified {$sent} subscribed players about \"{$challenge->title}\".");
    }
}
