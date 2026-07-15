<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
*/

// Schedule tasks
Schedule::command('horizon:snapshot')->everyFiveMinutes();
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Custom scheduled tasks
Schedule::command('sms:check-overdue-fees')->dailyAt('08:00');
Schedule::command('sms:check-overdue-books')->dailyAt('09:00');
Schedule::command('sms:send-attendance-summary')->dailyAt('17:00');
Schedule::command('sms:generate-monthly-reports')->monthlyOn(1, '06:00');
Schedule::command('sms:cleanup-temp-files')->weekly();
