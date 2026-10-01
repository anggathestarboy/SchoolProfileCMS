<?php

namespace App\Modules\ActivityLog\Jobs;

use App\Modules\ActivityLog\Models\ActivityLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Writes one activity_logs row — queued so logging an action never adds
 * latency to the save/delete/etc. it's describing (see
 * App\Modules\ActivityLog\Services\ActivityLogger, which dispatches this).
 */
class RecordActivityLog implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public readonly ?string $userId,
        public readonly string $action,
        public readonly ?string $subjectType,
        public readonly ?string $subjectId,
        public readonly string $description,
        public readonly array $changes = [],
        public readonly array $properties = [],
        public readonly ?string $occurredAt = null,
    ) {}

    public function handle(): void
    {
        ActivityLog::create([
            'user_id' => $this->userId,
            'action' => $this->action,
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'description' => $this->description,
            'changes' => $this->changes,
            'properties' => $this->properties,
            'created_at' => $this->occurredAt ?? now(),
        ]);
    }
}
