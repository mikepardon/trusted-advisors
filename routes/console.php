<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:process-expired-turns')->everyMinute();
// Pairs waiting duel queues server-side so correspondence players matched while away.
Schedule::command('app:process-matchmaking')->everyMinute();
Schedule::command('app:process-season-end')->dailyAt('00:05');
// Runs after season-end so the expired season is already closed before a new one opens.
Schedule::command('app:generate-monthly-season')->dailyAt('00:10');
Schedule::command('app:generate-weekly-challenge')->weeklyOn(1, '00:01');
// Keep a rolling 6-day window of daily challenges generated ahead of time. Generation now
// tunes each challenge to be winnable and caches its bot win rate.
Schedule::command('app:generate-daily-challenge --ahead=6')->dailyAt('00:12');
// Re-verify winnability of today's and upcoming challenges (in case content changed after
// they were generated), re-tuning any that a bot can no longer win.
Schedule::command('app:rebuild-daily-challenges --upcoming')->dailyAt('00:20');
// Morning nudge once the day's challenge is live and blurbs are written.
Schedule::command('app:notify-daily-challenge')->dailyAt('08:00');
Schedule::command('app:process-league-week')->weeklyOn(1, '00:02');
Schedule::command('app:check-expiring-subscriptions')->dailyAt('02:00');
Schedule::command('app:process-scheduled-gifts')->everyMinute();
