<?php

namespace App\Modules\Media\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AssetMeta extends Model
{
    use LogsActivity;

    protected $table = 'assets_meta';

    protected $fillable = [
        'container',
        'folder',
        'basename',
        'filename',
        'extension',
        'path',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    public function containerModel(): BelongsTo
    {
        return $this->belongsTo(AssetContainer::class, 'container', 'handle');
    }

    public function getUrlAttribute(): string
    {
        $disk = $this->containerModel?->disk ?? 'public';
        $scopedPath = AssetContainer::scopedPath($this->container, $this->path);

        if ($disk === 'public') {
            // Video needs to go through the app (see MediaServiceProvider) instead of the
            // storage symlink, so the response can honor Range requests — required for
            // browsers to seek to a trailing moov atom and actually play the file.
            if (str_starts_with((string) $this->mime_type, 'video/')) {
                return route('media.stream.public', ['path' => $scopedPath]);
            }

            return Storage::disk($disk)->url($scopedPath);
        }

        // Non-public disks are served through Laravel's local disk serving route,
        // which validates a *relative* signature, so the URL must be signed as relative too.
        // Cached (shorter than the signature's own 5-minute expiry) so the URL stays the
        // same across the many Livewire re-renders a page does while an asset is open —
        // otherwise every re-render mints a new signature, the <video src> attribute
        // changes, and the browser reloads the file from scratch, losing playback position.
        return Cache::remember(
            "asset-url:{$disk}:{$scopedPath}",
            now()->addMinutes(4),
            fn () => URL::temporarySignedRoute('storage.'.$disk, now()->addMinutes(5), ['path' => $scopedPath], absolute: false)
        );
    }

    public function absolutePath(): string
    {
        $disk = $this->containerModel?->disk ?? 'public';
        $scopedPath = AssetContainer::scopedPath($this->container, $this->path);

        return Storage::disk($disk)->path($scopedPath);
    }

    public function getSizeAttribute()
    {
        return data_get($this->meta, 'size');
    }

    public function getMimeTypeAttribute()
    {
        return data_get($this->meta, 'mime_type');
    }

    public function getWidthAttribute()
    {
        return data_get($this->meta, 'width');
    }

    public function getHeightAttribute()
    {
        return data_get($this->meta, 'height');
    }

    public function getTitleAttribute()
    {
        return data_get($this->meta, 'data.title') ?: $this->humanizedFilename();
    }

    public function getAltAttribute()
    {
        return data_get($this->meta, 'data.alt') ?: $this->humanizedFilename();
    }

    /**
     * A readable default for title/alt derived from the file's name — e.g.
     * "summer-sale_banner.jpg" becomes "Summer Sale Banner" — used until an
     * admin sets an explicit value (see getTitleAttribute()/getAltAttribute()).
     */
    protected function humanizedFilename(): string
    {
        return (string) Str::of($this->filename)->replace(['-', '_'], ' ')->squish()->title();
    }

    public function getLoadingAttribute()
    {
        return data_get($this->meta, 'data.loading') ?: 'lazy';
    }

    public function activityLabel(): string
    {
        return "asset '{$this->basename}.{$this->extension}'";
    }
}
