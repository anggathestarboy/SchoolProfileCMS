<?php

namespace App\Modules\ActivityLog\Jobs;

use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Models\ActivityLogDeletionRequest;
use App\Modules\ActivityLog\Services\ActivityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Hard-deletes (no soft deletes — see ActivityLog's own $timestamps=false,
 * append-only design) every activity_logs row for one confirmed request's
 * year. Only ever dispatched by the nightly activity-log:process-deletions
 * command for requests already confirmed via the emailed token link.
 */
class HardDeleteActivityLogYear implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $deletionRequestId) {}

    public function handle(): void
    {
        $request = ActivityLogDeletionRequest::find($this->deletionRequestId);

        if (! $request || $request->status !== 'confirmed') {
            return;
        }

        $from = "{$request->year}-01-01 00:00:00";
        $to = "{$request->year}-12-31 23:59:59";

        $deleted = ActivityLog::query()
            ->whereBetween('created_at', [$from, $to])
            ->delete();

        $request->update([
            'status' => 'executed',
            'executed_at' => now(),
            'deleted_count' => $deleted,
        ]);

        ActivityLogger::log(
            action: 'activity_log_year_deleted',
            subject: null,
            description: "Permanently deleted {$deleted} activity log entries from {$request->year}",
            properties: ['year' => $request->year, 'deleted_count' => $deleted, 'requested_by' => $request->requested_by],
        );
    }
}
