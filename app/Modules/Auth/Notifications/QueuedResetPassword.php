<?php

namespace App\Modules\Auth\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Identical to Laravel's stock ResetPassword notification — same email,
 * same token/URL — except queued, so sending it (from the "Forgot password"
 * form or an admin's "Send password reset" action, both routed through
 * User::sendPasswordResetNotification()) doesn't block that request on a
 * slow SMTP connection. Requires a real queue worker running (see
 * docker/supervisord.conf's "queue-worker" program) — without one, the
 * notification sits in the "jobs" table until one picks it up.
 */
class QueuedResetPassword extends ResetPassword implements ShouldQueue
{
    use Queueable;
}
