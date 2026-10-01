<?php

use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Sites\Models\Site;

test('draft:setup chains user creation, default site, the pages collection, the homepage blueprint and entry, and the admin sidebar', function () {
    $this->artisan('draft:setup', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'password123',
        '--site-name' => 'My Site',
        '--site-handle' => 'main',
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();
    expect($user->isSuper())->toBeTrue();

    $site = Site::sole();
    expect($site->handle)->toBe('main');

    $pages = Collection::where('handle', 'pages')->sole();
    expect($pages->structured())->toBeTrue();
    expect($pages->orderable())->toBeTrue();
    expect($pages->livePreview())->toBeTrue();
    expect($pages->schemaEnabled())->toBeTrue();
    expect($pages->seoEnabled())->toBeTrue();

    $blueprint = Blueprint::where('namespace', 'collections.pages')->where('handle', 'homepage')->sole();
    expect($blueprint->data['template'])->toBe('pages.static.homepage');

    $entry = Entry::where('collection', 'pages')->sole();
    expect($entry->blueprint)->toBe('homepage');
    expect($entry->order)->toBe(1);
    expect($entry->status)->toBe('published');

    expect(Navigation::where('handle', 'sidebar-admin')->exists())->toBeTrue();

    $container = AssetContainer::where('handle', 'assets')->sole();
    expect($container->disk)->toBe('public');
});

test('draft:setup stops before creating anything else when the admin step fails', function () {
    User::factory()->create();

    $this->artisan('draft:setup', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'password123',
    ])->assertFailed();

    expect(Site::count())->toBe(0);
    expect(Collection::count())->toBe(0);
    expect(Blueprint::count())->toBe(0);
    expect(Entry::count())->toBe(0);
    expect(Navigation::where('handle', 'sidebar-admin')->exists())->toBeFalse();
});

test('draft:setup does not create a second default site if one already exists', function () {
    Site::create(['handle' => 'existing', 'name' => 'Existing', 'url' => 'https://example.com', 'locale' => 'en', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    $this->artisan('draft:setup', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'password123',
    ])->assertSuccessful();

    expect(Site::count())->toBe(1);
    expect(Site::sole()->handle)->toBe('existing');
});
