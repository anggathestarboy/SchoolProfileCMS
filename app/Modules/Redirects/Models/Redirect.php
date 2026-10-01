<?php

namespace App\Modules\Redirects\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $source
 * @property string $target
 * @property int $status_code
 * @property string $match_type
 * @property bool $is_active
 * @property int $hits
 * @property Carbon|null $start_at
 * @property Carbon|null $end_at
 * @property bool $is_external
 * @property bool $keep_path
 */
class Redirect extends Model
{
    use LogsActivity;

    protected $table = 'redirects';

    protected $fillable = [
        'source',
        'target',
        'status_code',
        'match_type',
        'is_active',
        'hits',
        'start_at',
        'end_at',
        'is_external',
        'keep_path',
    ];

    protected $casts = [
        'status_code' => 'integer',
        'is_active' => 'boolean',
        'hits' => 'integer',
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'is_external' => 'boolean',
        'keep_path' => 'boolean',
    ];

    public function activityLabel(): string
    {
        return "redirect '{$this->source}' → '{$this->target}'";
    }

    /**
     * "hits" increments on every public visitor that follows this redirect
     * — that's traffic, not an admin action, and would otherwise flood the
     * log with an entry per pageview. Excluding it means a save that only
     * bumped the hit counter logs nothing at all (see LogsActivity's
     * empty-changes guard); a real edit alongside it still gets logged
     * normally, just without "hits" in the diff.
     *
     * @return array<int, string>
     */
    public function activityExcludedFromChanges(): array
    {
        return ['hits'];
    }
}
