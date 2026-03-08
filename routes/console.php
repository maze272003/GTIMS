<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\HoldService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('jm', function () {
    // server start
    passthru('php artisan serve');
})->purpose('start the server');

Artisan::command('holds:expire', function () {
    $count = app(HoldService::class)->expireHolds();
    $this->info("Expired {$count} holds.");
})->purpose('Expire holds that have passed their expiry date');

// Workflow automation scheduler
Schedule::command('workflows:run-scheduled')->everyMinute()->withoutOverlapping();
Schedule::command('workflows:retry-failed --limit=10')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('holds:expire')->hourly();
