<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SLA Escalation Check - Run every minute
Schedule::command('sla:check')->everyMinute()->description('Check SLA escalation for tickets');

// Clean old activity logs - Run weekly
Schedule::command('activity-logs:clean')->weekly()->description('Clean old activity logs');
