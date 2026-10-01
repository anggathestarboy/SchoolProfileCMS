<?php

namespace App\Modules\ActivityLog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One row per tracked admin action — see Concerns\LogsActivity (attaches to
 * a model for automatic create/update/delete logging) and
 * Services\ActivityLogger (for logging anything else: pivot changes,
 * ban/unban, login/logout, etc.). Write-once/append-only — nothing in this
 * app ever edits or soft-deletes a row here, and there's deliberately no
 * pruning: entries are kept indefinitely.
 *
 * @property int $id
 * @property string|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string $description
 * @property array|null $changes
 * @property array|null $properties
 */
class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'action', 'subject_type', 'subject_id', 'description', 'changes', 'properties', 'created_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'properties' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
