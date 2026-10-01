<?php

namespace App\Modules\Sitemap\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One <url> entry in the public sitemap.xml — kept in sync with entries by
 * SitemapSyncer (see Entry::booted()) rather than computed on the fly, so
 * sitemap.xml is a cheap read even with many entries.
 *
 * @property int $id
 * @property string|null $origin_id references entries.id
 * @property string $collection
 * @property string $url
 * @property string $changefreq
 * @property float $priority
 * @property Carbon|null $lastmod
 * @property bool $is_active
 * @property string $site
 */
class Sitemap extends Model
{
    protected $table = 'sitemaps';

    protected $fillable = [
        'origin_id', 'collection', 'url', 'changefreq', 'priority', 'lastmod', 'is_active', 'site',
    ];

    protected $casts = [
        'priority' => 'float',
        'lastmod' => 'datetime',
        'is_active' => 'boolean',
    ];
}
