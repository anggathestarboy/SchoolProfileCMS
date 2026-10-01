<?php

namespace App\Modules\Collections\Http\Controllers;

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Collections\Services\EntryUrlResolver;
use App\Modules\Sites\Models\Site;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public-facing catch-all: renders a published entry at the URL resolved
 * from its collection's route template (see Collection::routeForSite() and
 * EntryUrlResolver), using the Blade template picked in its blueprint
 * (Blueprint::data.template — see BlueprintManager).
 */
class PageController
{
    public function show(Request $request, string $uri = ''): View
    {
        [$site, $relativeUri] = $this->resolveSite($request, trim($uri, '/'));

        if (! $site) {
            throw new NotFoundHttpException;
        }

        $entry = $this->findEntry($site->handle, $relativeUri);

        if (! $entry) {
            throw new NotFoundHttpException;
        }

        $template = Blueprint::where('handle', $entry->blueprint)->value('data');
        $template = $template['template'] ?? null;

        if (! $template || ! view()->exists($template)) {
            throw new NotFoundHttpException;
        }

        return view($template, [
            'entry' => $entry,
            'data' => $entry->fluentData(),
            'site' => $site,
        ]);
    }

    /**
     * Looks up a published entry by its cached uri first (fast, indexed).
     * If nothing matches — e.g. the entry predates uri being stored, or an
     * ancestor's slug changed since — falls back to live-resolving every
     * routable entry on this site and backfilling the match's uri for next
     * time.
     *
     * ponytail: O(n) routable entries on a cache miss; fine at this scale,
     * revisit (e.g. resolve+store on every save cascading to descendants)
     * if this becomes a hot path with many entries.
     */
    protected function findEntry(string $site, string $uri): ?Entry
    {
        $entry = Entry::where('site', $site)->where('status', 'published')->where('uri', $uri)->first();

        if ($entry) {
            return $entry;
        }

        $resolver = app(EntryUrlResolver::class);
        $routableCollections = Collection::all()->filter(fn (Collection $collection): bool => $collection->routeForSite($site) !== null);

        foreach ($routableCollections as $collection) {
            $candidates = Entry::where('collection', $collection->handle)
                ->where('site', $site)
                ->where('status', 'published')
                ->get();

            foreach ($candidates as $candidate) {
                if ($resolver->path($collection, $candidate) === $uri) {
                    $candidate->update(['uri' => $uri]);

                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Matches a request to a site, supporting both domain-based sites
     * (url has a host, e.g. https://en.example.com) and path-prefix-based
     * sites sharing one domain (url is just a path, e.g. "/en") — Sites
     * here store either, per the Sites module. A site with a host only
     * matches that same request host; a site's path (if any) must prefix
     * the request path. The longest matching path prefix wins, so "/en"
     * beats the root site "/" for a request under /en. Returns the site
     * and the path with that prefix stripped, ready to look up an entry's
     * stored uri.
     *
     * ponytail: no wildcard/subdomain matching, no scheme check — enough
     * for one domain (or none, in local dev) with per-language path
     * prefixes; revisit if sites start spanning multiple real domains.
     *
     * @return array{0: ?Site, 1: string}
     */
    public function resolveSite(Request $request, string $path): array
    {
        $host = $request->getHost();
        $best = null;
        $bestPrefix = null;

        foreach (Site::orderBy('order')->get() as $site) {
            $parsed = parse_url($site->url ?: '/');
            $siteHost = $parsed['host'] ?? null;
            $sitePath = trim($parsed['path'] ?? '', '/');

            if ($siteHost && $siteHost !== $host) {
                continue;
            }

            $matches = $sitePath === '' || $path === $sitePath || str_starts_with($path, "{$sitePath}/");

            if ($matches && ($bestPrefix === null || strlen($sitePath) > strlen($bestPrefix))) {
                $best = $site;
                $bestPrefix = $sitePath;
            }
        }

        $best ??= Site::orderBy('order')->first();

        if (! $best) {
            return [null, $path];
        }

        $relative = $bestPrefix ? trim(substr($path, strlen($bestPrefix)), '/') : $path;

        return [$best, $relative];
    }
}
