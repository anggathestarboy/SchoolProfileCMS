<?php

namespace App\Modules\ActivityLog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A superadmin-only, "ribet by design" request to hard-delete every activity
 * log entry for one year. Nothing is deleted until (a) the request is
 * confirmed via the emailed token link and (b) the nightly
 * activity-log:process-deletions command picks it up — see
 * Jobs\HardDeleteActivityLogYear.
 *
 * @property int $id
 * @property int $year
 * @property string|null $requested_by
 * @property string $token_hash
 * @property string $status
 * @property Carbon $expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $executed_at
 * @property int|null $deleted_count
 */
class ActivityLogDeletionRequest extends Model
{
    protected $fillable = ['year', 'requested_by', 'token_hash', 'status', 'expires_at', 'confirmed_at', 'executed_at', 'deleted_count'];

    protected $casts = [
        'expires_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'executed_at' => 'datetime',
    ];

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isExpired(): bool
    {
        return $this->status === 'pending' && $this->expires_at->isPast();
    }
}
