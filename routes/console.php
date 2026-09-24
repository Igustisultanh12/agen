<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes & Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Check provider health every 5 minutes (Requirement 32)
Schedule::command('fcc:check-health')->everyFiveMinutes();

// Aggregate usage logs into daily stats hourly (Requirement 68)
Schedule::command('fcc:aggregate-usage')->hourly();

// Reset monthly user quota at the beginning of every month (Requirement 11)
Schedule::call(function () {
    app(\App\Services\AI\QuotaService::class)->resetMonthlyQuota();
})->monthly();
