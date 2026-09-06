<?php

use App\Models\AppTaskLog;
use Illuminate\Support\Facades\Schedule;

// Log the heartbeat of the scheduler
Schedule::call(function () {
    AppTaskLog::createHeartbeat();
})->everyMinute();

// Every day
Schedule::command('cronjobs:orders:renew-balance-renewals')->daily();
Schedule::command('cronjobs:update-currency-rates')->daily();

// every 3 hours
Schedule::command('cronjobs:orders:suspend-expired')->everyThreeHours();
Schedule::command('cronjobs:orders:terminate-expired')->everyThreeHours();
Schedule::command('cronjobs:report-active-check')->everyThreeHours()->withoutOverlapping();

// Every minute
Schedule::command('cronjobs:mass-mails:send')->everyMinute()->withoutOverlapping();

// Every five minutes
Schedule::command('server-connections:test')->everyFiveMinutes();

// Every thirty minutes
Schedule::command('cronjobs:check-github-update')->everyThirtyMinutes()->withoutOverlapping();
