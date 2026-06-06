<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('bookings:send-reminders')->hourly();
Schedule::command('system:health-check')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('exports:prune')->hourly()->withoutOverlapping();
Schedule::command('bookings:auto-release')->everyFiveMinutes()->withoutOverlapping();
