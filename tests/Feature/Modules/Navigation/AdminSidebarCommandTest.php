<?php

use App\Modules\Navigation\Models\Navigation;
use App\Modules\Navigation\Services\NavigationTreeService;

test('admin:sidebar creates the sidebar-admin navigation with default items', function () {
    $this->artisan('admin:sidebar')->assertSuccessful();

    $navigation = Navigation::where('handle', 'sidebar-admin')->sole();
    $tree = app(NavigationTreeService::class)->load($navigation);

    expect($tree)->toHaveCount(18);
    expect(collect($tree)->pluck('title')->all())->toBe([
        'Dashboard', 'Media Library', 'Collections', 'Pages', 'Taxonomies', 'Globals', 'Blueprints',
        'Fieldsets', 'Navigations', 'Forms', 'Redirect Manager', 'Sitemap Manager', 'Sites', 'Site Settings',
        'Users', 'Roles', 'User Groups', 'Activity Log',
    ]);
    expect($tree[0]['url'])->toBe('/admin-panel/dashboard');
});

test('admin:sidebar is idempotent unless --force is passed', function () {
    $this->artisan('admin:sidebar')->assertSuccessful();

    $navigation = Navigation::where('handle', 'sidebar-admin')->sole();
    app(NavigationTreeService::class)->addItem($navigation, null, ['type' => 'internal', 'title' => 'Custom Link', 'url' => '/custom']);

    $this->artisan('admin:sidebar')->assertSuccessful();

    $tree = app(NavigationTreeService::class)->load($navigation);
    expect($tree)->toHaveCount(19);
    expect($tree[18]['title'])->toBe('Custom Link');

    $this->artisan('admin:sidebar', ['--force' => true])->assertSuccessful();

    $tree = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($tree)->toHaveCount(18);
});
