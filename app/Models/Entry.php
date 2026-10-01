<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entry extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'site',
        'origin_id',
        'published',
        'slug',
        'uri',
        'date',
        'order',
        'collection',
        'blueprint',
        'data',
    ];

    protected $casts = [
        'published' => 'integer',
        'data' => 'array',
    ];

    public const STATUS_PUBLISHED = 1;

    public const STATUS_DRAFT = 2;

    public const STATUS_NEED_REVIEW = 3;

    public function getRouteKeyName(): string
    {
        return 'uri';
    }

    public function collectionModel(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collection', 'handle');
    }

    /** The "parent" entry this one was localized from (Statamic localization). */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'origin_id', 'id');
    }

    /** Localized copies of this entry across other sites. */
    public function localizations(): HasMany
    {
        return $this->hasMany(Entry::class, 'origin_id', 'id');
    }

    public function siteModel(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site', 'handle');
    }

    public function sitemapEntry(): HasMany
    {
        return $this->hasMany(Sitemap::class, 'origin_id', 'id');
    }

    public function scopePublished($query)
    {
        return $query->where('published', self::STATUS_PUBLISHED);
    }

    public function scopeBySite($query, string $handle)
    {
        return $query->where('site', $handle);
    }

    public function scopeInCollection($query, string $handle)
    {
        return $query->where('collection', $handle);
    }

    public function isLocalized(): bool
    {
        return ! is_null($this->origin_id);
    }

    public function getOriginSite(): ?self
    {
        if (! $this->origin_id) {
            return null;
        }

        return self::where('id', $this->origin_id)->first();
    }

    public function getLocalizedSites(): \Illuminate\Database\Eloquent\Collection
    {
        if ($this->isLocalized()) {
            return self::where('origin_id', $this->origin_id)
                ->where('site', '!=', $this->site)
                ->get();
        }

        return $this->localizations;
    }

    public function getLocalizedCount(): int
    {
        return $this->getLocalizedSites()->count();
    }
}
