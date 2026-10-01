<?php

namespace App\Modules\Sitemap\Http\Controllers;

use App\Modules\Sitemap\Models\Sitemap;
use Illuminate\Http\Response;

/**
 * Serves the public sitemap — split per collection so each stays a
 * manageable size and is easy to regenerate/inspect independently, the
 * standard "sitemap index" pattern (https://www.sitemaps.org/protocol.html#index).
 * /sitemap.xml is the index, listing one <sitemap> per collection that has
 * at least one active URL; /sitemap-{collection}.xml is that collection's
 * own <urlset>. Both read from the sitemaps table kept in sync by
 * SitemapSyncer (see Entry::booted()).
 */
class SitemapController
{
    public function index(): Response
    {
        $collections = Sitemap::where('is_active', true)
            ->selectRaw('collection, max(lastmod) as lastmod')
            ->groupBy('collection')
            ->orderBy('collection')
            ->get();

        return response()
            ->view('sitemap::sitemap-index', ['collections' => $collections])
            ->header('Content-Type', 'text/xml');
    }

    public function collection(string $collection): Response
    {
        $urls = Sitemap::where('is_active', true)
            ->where('collection', $collection)
            ->orderBy('url')
            ->get();

        abort_if($urls->isEmpty(), 404);

        return response()
            ->view('sitemap::sitemap', ['urls' => $urls, 'collection' => $collection])
            ->header('Content-Type', 'text/xml');
    }

    /**
     * The XSLT stylesheet both sitemap responses reference (see their
     * "<?xml-stylesheet?>" processing instruction) — browsers render it
     * into a readable, CMS-styled page, while crawlers ignore it and read
     * the raw XML underneath. Served through a route (not public/) so the
     * Content-Type is always correct regardless of web server config.
     */
    public function stylesheet(): Response
    {
        return response(
            file_get_contents(__DIR__.'/../../resources/xsl/sitemap.xsl'),
            200,
            ['Content-Type' => 'text/xsl'],
        );
    }
}
