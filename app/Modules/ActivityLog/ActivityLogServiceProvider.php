<?php

namespace App\Modules\ActivityLog;

use App\Modules\ActivityLog\Console\Commands\ProcessActivityLogDeletionsCommand;
use App\Modules\ActivityLog\Listeners\LogAuthenticationEvents;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Read-only audit trail of admin activity — automatic create/update/delete
 * logging for any model that `use`s Concerns\LogsActivity, plus explicit
 * App\Modules\ActivityLog\Services\ActivityLogger::log() calls for
 * everything else (pivot changes, ban/unban, password resets, login/logout
 * — see LogAuthenticationEvents below).
 * Owns tables: activity_logs.
 */
class ActivityLogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'activity-log');

        $this->commands([
            ProcessActivityLogDeletionsCommand::class,
        ]);

        Event::listen(Login::class, [LogAuthenticationEvents::class, 'handleLogin']);
        Event::listen(Logout::class, [LogAuthenticationEvents::class, 'handleLogout']);
        Event::listen(Failed::class, [LogAuthenticationEvents::class, 'handleFailed']);

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('activity-log.')
            ->group(__DIR__.'/routes.php');
    }
}
