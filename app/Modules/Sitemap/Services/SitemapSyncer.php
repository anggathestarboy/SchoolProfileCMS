<?php

namespace App\Modules\Sitemap\Services;

use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Collections\Services\EntryUrlResolver;
use App\Modules\Sitemap\Models\Sitemap;

/**
 * Keeps the sitemaps table (one row per public entry URL) in sync with
 * entries — called from Entry::booted() on every save/delete, so
 * sitemap.xml is always a cheap read of already-resolved rows rather than
 * something computed from every entry on each request.
 */
class SitemapSyncer
{
    const DEFAULT_PRIORITY = 0.5;

    const DEFAULT_CHANGEFREQ = 'weekly';

    public function __construct(protected EntryUrlResolver $resolver) {}

    /**
     * Upserts this entry's sitemap row, or removes it if the entry is no
     * longer eligible (unpublished, or its collection has no public route
     * for this site).
     */
    public function sync(Entry $entry): void
    {
        $collection = Collection::where('handle', $entry->collection)->first();
        $url = $collection ? $this->resolver->url($collection, $entry) : null;
        $seoEnabled = $collection?->seoEnabled() ?? false;
        $noindexed = $seoEnabled && in_array('noindex', $entry->seoFields()['robots'] ?? [], true);

        if (! $collection || $url === null || $entry->status !== 'published' || $noindexed) {
            $this->remove($entry);

            return;
        }

        [$priority, $changefreq] = $seoEnabled
            ? [(float) ($entry->seoFields()['sitemap_priority'] ?? self::DEFAULT_PRIORITY), (string) ($entry->seoFields()['sitemap_changefreq'] ?? self::DEFAULT_CHANGEFREQ)]
            : [self::DEFAULT_PRIORITY, self::DEFAULT_CHANGEFREQ];

        Sitemap::updateOrCreate(
            ['site' => $entry->site, 'collection' => $entry->collection, 'origin_id' => $entry->id],
            ['url' => $url, 'changefreq' => $changefreq, 'priority' => $priority, 'lastmod' => $entry->updated_at, 'is_active' => true],
        );
    }

    public function remove(Entry $entry): void
    {
        Sitemap::where('site', $entry->site)
            ->where('collection', $entry->collection)
            ->where('origin_id', $entry->id)
            ->delete();
    }
}
