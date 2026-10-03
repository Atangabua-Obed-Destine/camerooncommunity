<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Location history: keep the trail to its 90-day window ───
Schedule::command('locations:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping(10)
    ->runInBackground();

// ─── Marketplace: scan saved searches and notify users hourly ───
Schedule::command('marketplace:run-saved-searches')
    ->hourly()
    ->withoutOverlapping(10)
    ->runInBackground();
