<?php

namespace App\Modules\Media\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetContainer extends Model
{
    use LogsActivity;

    protected $fillable = ['handle', 'title', 'disk', 'settings'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function assets(): HasMany
    {
        return $this->hasMany(AssetMeta::class, 'container', 'handle');
    }

    /**
     * Every container has its own root folder on disk, named after its handle,
     * so containers sharing a disk never collide on the same physical path.
     */
    public static function scopedPath(string $handle, string $path): string
    {
        return trim($handle.'/'.$path, '/');
    }

    public function activityLabel(): string
    {
        return "media container '{$this->title}'";
    }
}
