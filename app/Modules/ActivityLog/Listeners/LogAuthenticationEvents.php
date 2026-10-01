<?php

namespace App\Modules\ActivityLog\Listeners;

use App\Modules\ActivityLog\Services\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Login/logout aren't model create/update/delete events, so they can't go
 * through Concerns\LogsActivity — hooked onto Laravel's own auth events
 * instead (fired by Fortify's login/logout controllers) and registered in
 * ActivityLogServiceProvider::boot().
 */
class LogAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        ActivityLogger::log(
            action: 'logged_in',
            subject: $event->user,
            description: "{$event->user->name} logged in",
            properties: ['ip' => request()->ip()],
        );
    }

    public function handleLogout(Logout $event): void
    {
        if (! $event->user) {
            return;
        }

        ActivityLogger::log(
            action: 'logged_out',
            subject: $event->user,
            description: "{$event->user->name} logged out",
            properties: ['ip' => request()->ip()],
        );
    }

    public function handleFailed(Failed $event): void
    {
        $email = $event->credentials['email'] ?? 'unknown';

        ActivityLogger::log(
            action: 'login_failed',
            subject: $event->user,
            description: "Failed login attempt for {$email}",
            properties: ['ip' => request()->ip(), 'email' => $email],
        );
    }
}
