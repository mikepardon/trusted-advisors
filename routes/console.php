<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:process-expired-turns')->everyMinute();
Schedule::command('app:process-season-end')->dailyAt('00:05');
// Runs after season-end so the expired season is already closed before a new one opens.
Schedule::command('app:generate-monthly-season')->dailyAt('00:10');
Schedule::command('app:generate-weekly-challenge')->weeklyOn(1, '00:01');
Schedule::command('app:process-league-week')->weeklyOn(1, '00:02');
Schedule::command('app:check-expiring-subscriptions')->dailyAt('02:00');
Schedule::command('app:process-scheduled-gifts')->everyMinute();
