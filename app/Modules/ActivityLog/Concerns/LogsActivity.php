<?php

namespace App\Modules\ActivityLog\Concerns;

use App\Modules\ActivityLog\Services\ActivityLogger;
use Illuminate\Support\Str;

/**
 * Adds automatic activity-log entries for a model's create/update/delete —
 * `use LogsActivity;` on any Eloquent model is the entire integration, no
 * override needed for a serviceable default description. Relies on
 * Laravel's bootLogsActivity() naming convention (see
 * Model::bootTraits()), so this coexists cleanly with a model's own
 * booted()/boot() (e.g. Entry's SitemapSyncer hook) — both run, neither
 * overrides the other.
 *
 * A model can optionally improve on the generic output by defining:
 * - activityLabel(): string — an identifying label, e.g. "entry 'About
 *   us'" instead of the default "entry #01a...".
 * - activityAction(string $default, array $changes): string — relabel the
 *   verb for a specific field change, e.g. User turning banned_at from
 *   null to a timestamp becomes "banned" instead of "updated".
 * - activityExcludedFromChanges(): array<int, string> — column names to
 *   leave out of the logged diff (the "updated" entry still gets logged,
 *   just without these) — for a large/rarely-useful JSON column that would
 *   otherwise bloat every entry, e.g. Entry's schema/seo blocks.
 */
trait LogsActivity
{
    protected static function bootLogsActivity(): void
    {
        static::created(function ($model): void {
            static::recordActivity('created', $model);
        });

        static::updated(function ($model): void {
            $changes = static::activityChanges($model);

            // A save that only touched excluded/hidden columns (or nothing
            // at all) isn't worth an entry — avoids empty-diff noise.
            if ($changes === []) {
                return;
            }

            static::recordActivity('updated', $model, $changes);
        });

        static::deleted(function ($model): void {
            static::recordActivity('deleted', $model);
        });
    }

    /**
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    protected static function recordActivity(string $action, $model, array $changes = []): void
    {
        $action = method_exists($model, 'activityAction') ? $model->activityAction($action, $changes) : $action;
        $actor = auth()->user()?->name ?? 'System';
        $label = static::activityLabelFor($model);

        ActivityLogger::log(
            action: $action,
            subject: $model,
            description: "{$actor} {$action} {$label}",
            changes: $changes,
        );
    }

    protected static function activityLabelFor($model): string
    {
        if (method_exists($model, 'activityLabel')) {
            return (string) $model->activityLabel();
        }

        return Str::lower(class_basename($model))." #{$model->getKey()}";
    }

    /**
     * @return array<string, array{from: mixed, to: mixed}>
     */
    protected static function activityChanges($model): array
    {
        $dirty = $model->getChanges();
        unset($dirty['updated_at']);

        $hidden = $model->getHidden();
        $excluded = method_exists($model, 'activityExcludedFromChanges') ? $model->activityExcludedFromChanges() : [];
        $changes = [];

        foreach ($dirty as $key => $newValue) {
            if (in_array($key, $hidden, true) || in_array($key, $excluded, true)) {
                continue;
            }

            $changes[$key] = [
                'from' => $model->getOriginal($key),
                'to' => $newValue,
            ];
        }

        return $changes;
    }
}
