<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Marketplace: scan saved searches and notify users hourly ───
Schedule::command('marketplace:run-saved-searches')
    ->hourly()
    ->withoutOverlapping(10)
    ->runInBackground();
