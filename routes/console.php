<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| PMO Automation Schedule (times from .env via config/pmo.php)
|--------------------------------------------------------------------------
| Requires the Laravel scheduler: `php artisan schedule:work` (local) or a
| cron entry running `php artisan schedule:run` every minute (server).
*/

Schedule::command('pmo:send-daily-standup')
    ->dailyAt((string) config('pmo.standup_time', '08:30'))
    ->timezone((string) config('pmo.timezone', 'Asia/Kuala_Lumpur'))
    ->withoutOverlapping();

Schedule::command('pmo:check-overdue-tasks')
    ->dailyAt((string) config('pmo.overdue_check_time', '09:00'))
    ->timezone((string) config('pmo.timezone', 'Asia/Kuala_Lumpur'))
    ->withoutOverlapping();
