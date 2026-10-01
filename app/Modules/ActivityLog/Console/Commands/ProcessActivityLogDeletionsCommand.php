<?php

namespace App\Modules\ActivityLog\Console\Commands;

use App\Modules\ActivityLog\Jobs\HardDeleteActivityLogYear;
use App\Modules\ActivityLog\Models\ActivityLogDeletionRequest;
use Illuminate\Console\Command;

/**
 * Runs nightly at 00:00 (see routes/console.php) — dispatches the heavy hard
 * delete for every deletion request a superadmin has confirmed via the
 * emailed token link, and expires any still-pending request whose 24 hour
 * confirmation window has passed.
 */
class ProcessActivityLogDeletionsCommand extends Command
{
    protected $signature = 'activity-log:process-deletions';

    protected $description = 'Execute confirmed activity log deletion requests and expire stale ones';

    public function handle(): int
    {
        $confirmed = ActivityLogDeletionRequest::where('status', 'confirmed')->get();

        foreach ($confirmed as $request) {
            HardDeleteActivityLogYear::dispatch($request->id);
        }

        $expired = ActivityLogDeletionRequest::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        $this->info("Dispatched {$confirmed->count()} deletion(s), expired {$expired} stale request(s).");

        return self::SUCCESS;
    }
}
