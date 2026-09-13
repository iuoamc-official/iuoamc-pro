<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('journal:dispatch-notifications --limit=50')
    ->everyMinute()
    ->withoutOverlapping(10)
    ->onOneServer();

Schedule::command('journal:queue-review-reminders --days=3 --limit=100')
    ->dailyAt('08:00')
    ->withoutOverlapping(60)
    ->onOneServer();
