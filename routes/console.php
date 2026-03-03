<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:process-expired-turns')->everyMinute();
Schedule::command('app:process-season-end')->dailyAt('00:05');
Schedule::command('app:generate-weekly-challenge')->weeklyOn(1, '00:01');
