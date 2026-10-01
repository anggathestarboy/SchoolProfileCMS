<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<?php echo '<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>'; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($collections as $collection)
    <sitemap>
        <loc>{{ route('sitemap.collection', ['collection' => $collection->collection]) }}</loc>
        @if ($collection->lastmod)
            <lastmod>{{ \Illuminate\Support\Carbon::parse($collection->lastmod)->toAtomString() }}</lastmod>
        @endif
    </sitemap>
@endforeach
</sitemapindex>
