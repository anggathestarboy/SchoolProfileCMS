<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Mirrors Statamic's scheduled-content cron (statamic.dev/advanced-topics/scheduling)
// — see PublishScheduledEntriesCommand. Only fires anything once the server
// actually runs `php artisan schedule:run` every minute via a real cron entry
// (`* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1`).
Schedule::command('entries:publish-scheduled')->everyMinute();

// Executes confirmed activity log deletion requests — see
// ActivityLogDeletionRequest / HardDeleteActivityLogYear.
Schedule::command('activity-log:process-deletions')->dailyAt('00:00');
