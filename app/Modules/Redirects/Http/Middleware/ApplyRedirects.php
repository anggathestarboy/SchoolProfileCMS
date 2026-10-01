<?php

namespace App\Modules\Redirects\Http\Middleware;

use App\Modules\Redirects\Models\Redirect;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies active redirect rules to public, front-end requests before they
 * reach the page/entry resolver (see routes/web.php's catch-all
 * PageController route) — a matching redirect always wins over rendering a
 * page at that URL.
 *
 * ponytail: loads every active-and-in-window redirect and matches in PHP
 * rather than pushing exact/prefix/regex matching into SQL; fine at the
 * scale a hand-maintained redirects list stays at, revisit (e.g. an indexed
 * exact-match lookup first) if this table grows large.
 */
class ApplyRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->is(config('cms.route')) || $request->is(config('cms.route').'/*')) {
            return $next($request);
        }

        $path = '/'.ltrim($request->path(), '/');
        $now = now();

        $redirect = Redirect::where('is_active', true)
            ->where(fn ($query) => $query->whereNull('start_at')->orWhere('start_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('end_at')->orWhere('end_at', '>=', $now))
            ->get()
            ->first(fn (Redirect $redirect): bool => $this->matches($redirect, $path));

        if (! $redirect) {
            return $next($request);
        }

        $redirect->increment('hits');
        $target = $this->targetFor($redirect, $path);

        // 410/451 are terminal statuses, not redirects — Symfony's
        // RedirectResponse rejects any non-3xx code outright, and browsers
        // never follow a Location header on a non-3xx response by spec. To
        // still send visitors to the target while reporting that status
        // (e.g. for crawlers/legal compliance), do it client-side instead.
        $response = in_array($redirect->status_code, [410, 451], true)
            ? response($this->clientRedirectHtml($target), $redirect->status_code)
            : ($redirect->is_external
                ? redirect()->away($target, $redirect->status_code)
                : redirect()->to($target, $redirect->status_code));

        // Browsers cache a 301 aggressively and will keep reusing it locally
        // — without a hint against that, the record's hit count stops
        // incrementing after the first real visit, and toggling the rule
        // inactive (or editing it) looks like it did nothing until the
        // visitor's cache expires. These are admin-editable rules, not
        // static server redirects, so force a revalidation every time.
        return $response->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    protected function matches(Redirect $redirect, string $path): bool
    {
        return match ($redirect->match_type) {
            'exact' => $redirect->source === $path,
            'prefix' => str_starts_with($path, $redirect->source),
            'regex' => @preg_match($redirect->source, $path) === 1,
            default => false,
        };
    }

    protected function clientRedirectHtml(string $target): string
    {
        return '<!doctype html><html><head><meta http-equiv="refresh" content="0;url='.e($target).'"></head>'
            .'<body><script>window.location.replace('.json_encode($target).');</script></body></html>';
    }

    protected function targetFor(Redirect $redirect, string $path): string
    {
        $target = $redirect->target;

        // "Keep path" only makes sense when the match consumed a leading
        // part of the request path — append whatever's left after that
        // match onto the target, whether the match came from a literal
        // prefix or a regex pattern.
        if ($redirect->keep_path && $redirect->match_type === 'prefix') {
            $target = rtrim($target, '/').substr($path, strlen($redirect->source));
        } elseif ($redirect->keep_path && $redirect->match_type === 'regex' && @preg_match($redirect->source, $path, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $matchedLength = $matches[0][1] + strlen($matches[0][0]);
            $target = rtrim($target, '/').substr($path, $matchedLength);
        }

        // A Location header with no scheme (e.g. "example.com") is a
        // relative reference per the URL spec, so browsers resolve it
        // against the current site instead of leaving it — admins naturally
        // type domains without "https://", so assume that scheme rather
        // than silently keeping visitors on-site for an "external" redirect.
        if ($redirect->is_external && ! preg_match('#^[a-z][a-z0-9+.-]*://#i', $target)) {
            $target = 'https://'.ltrim($target, '/');
        }

        return $target;
    }
}
