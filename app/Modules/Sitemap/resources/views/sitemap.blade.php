<?php echo '<?xml version="1.0" encoding="UTF-8"?>'; ?>
<?php echo '<?xml-stylesheet type="text/xsl" href="/sitemap.xsl"?>'; ?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<!-- collection: {{ $collection }} -->
@foreach ($urls as $url)
    <url>
        <loc>{{ $url->url }}</loc>
        @if ($url->lastmod)
            <lastmod>{{ $url->lastmod->toAtomString() }}</lastmod>
        @endif
        <changefreq>{{ $url->changefreq }}</changefreq>
        <priority>{{ number_format($url->priority, 1) }}</priority>
    </url>
@endforeach
</urlset>
