<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sitemap extends Model
{
    protected $fillable = [
        'origin_id', 'collection', 'url', 'changefreq', 'priority',
        'lastmod', 'is_active', 'site',
    ];

    protected $casts = [
        'priority' => 'decimal:1',
        'lastmod' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function collectionModel(): BelongsTo
    {
        return $this->belongsTo(Collection::class, 'collection', 'handle');
    }

    public function siteModel(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'site', 'handle');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(Entry::class, 'origin_id', 'id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
