<?php

use App\Models\User;
use App\Modules\Collections\Models\Collection;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Navigation\Services\NavigationTreeService;

/**
 * Reproduces a reported bug: visiting a nav item whose URL is a sub-path of
 * another nav item's URL (e.g. "Pages" at /admin-panel/collections/pages
 * under "Collections" at /admin-panel/collections) highlighted both as
 * active, because the sidebar matched active state with a "starts with"
 * wildcard instead of an exact path match.
 */
test('only the exact-matching sidebar item is marked active, not an ancestor-path sibling', function () {
    $this->actingAs(User::factory()->create());

    Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => []]);

    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);
    $trees = app(NavigationTreeService::class);
    $trees->addItem($navigation, null, ['type' => 'internal', 'title' => 'Collections', 'url' => '/admin-panel/collections']);
    $trees->addItem($navigation, null, ['type' => 'internal', 'title' => 'Pages', 'url' => '/admin-panel/collections/pages']);

    $html = $this->get('/admin-panel/collections/pages')->assertOk()->getContent();

    // Scope the match to sidebar items specifically (data-flux-sidebar-item)
    // since the page itself also links back to /admin-panel/collections via
    // an unrelated breadcrumb anchor.
    preg_match('/<a\b(?=[^>]*data-flux-sidebar-item)(?=[^>]*href="[^"]*\/admin-panel\/collections")[^>]*>/', $html, $collectionsAnchor);
    preg_match('/<a\b(?=[^>]*data-flux-sidebar-item)(?=[^>]*href="[^"]*\/admin-panel\/collections\/pages")[^>]*>/', $html, $pagesAnchor);

    expect($collectionsAnchor)->not->toBeEmpty();
    expect($pagesAnchor)->not->toBeEmpty();
    // The actual boolean attribute renders as `data-current="data-current"` —
    // note the tag's own Tailwind classes always contain `data-current:...`
    // (a CSS variant selector) regardless of active state, so the check must
    // require the `="` to avoid a false positive on those class names.
    expect(str_contains($collectionsAnchor[0], 'data-current="'))->toBeFalse();
    expect(str_contains($pagesAnchor[0], 'data-current="'))->toBeTrue();
});
