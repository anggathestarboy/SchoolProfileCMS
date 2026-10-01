<?php

namespace App\Modules\ActivityLog\Services;

use App\Modules\ActivityLog\Jobs\RecordActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * The one place every activity-log entry gets written from — both the
 * automatic path (Concerns\LogsActivity, for plain create/update/delete)
 * and every manual call site (pivot changes, ban/unban, login/logout, a
 * "send password reset" click, etc.) go through log() so there's exactly
 * one queueing/actor-resolution/timestamp policy to reason about.
 */
class ActivityLogger
{
    /**
     * @param  string  $action  A short verb: 'created', 'updated', 'deleted', 'banned', 'logged_in', ...
     * @param  Model|null  $subject  What the action was done to, if any single model applies.
     * @param  string  $description  A human-readable sentence, already fully formed (not a template).
     * @param  array<string, mixed>  $changes  Old/new values, when relevant (e.g. ['status' => ['from' => 'draft', 'to' => 'published']]).
     * @param  array<string, mixed>  $properties  Anything else worth keeping (IP, request context, ...).
     */
    public static function log(
        string $action,
        ?Model $subject,
        string $description,
        array $changes = [],
        array $properties = [],
    ): void {
        if (! config('activity-log.enabled')) {
            return;
        }

        RecordActivityLog::dispatch(
            userId: Auth::id(),
            action: $action,
            subjectType: $subject?->getMorphClass(),
            subjectId: $subject ? (string) $subject->getKey() : null,
            description: $description,
            changes: $changes,
            properties: $properties,
            occurredAt: now()->toDateTimeString(),
        );
    }
}
