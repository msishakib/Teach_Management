<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('accounting:rebuild-daily-balances')->dailyAt('01:15');

// Nightly database backup. Old backups are pruned to the 14-day retention
// window configured in config/backup.php.
Schedule::command('backup:run --only-db')
    ->dailyAt('02:00')
    ->withoutOverlapping();

Schedule::command('backup:clean')
    ->dailyAt('02:30')
    ->withoutOverlapping();

Schedule::command('backup:monitor')
    ->dailyAt('03:00')
    ->withoutOverlapping();

// Remove orphaned media files and upload chunks older than 24 hours.
Schedule::command('media:prune')
    ->dailyAt('03:30')
    ->withoutOverlapping();

