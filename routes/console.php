<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Auto print kanban TMMIN per cycle
// Schedule::command('kanban:tmmin-auto-print')
//     ->everyMinute()
//     ->withoutOverlapping(10)
//     ->appendOutputTo(storage_path('logs/autoprint.log'));