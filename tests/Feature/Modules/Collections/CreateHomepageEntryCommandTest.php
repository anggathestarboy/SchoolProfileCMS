<?php

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Sites\Models\Site;

function makeHomepageEntryPrereqs(): void
{
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://example.id', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 1]);
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => ['route_mode' => 'single', 'route' => '{parent_uri}/{slug}', 'structured' => true, 'expects_root' => true]]);
    Blueprint::create(['namespace' => 'collections.pages', 'handle' => 'homepage', 'data' => ['template' => 'pages.static.homepage', 'fields' => [
        ['handle' => 'heading', 'display' => 'Heading', 'type' => 'text', 'required' => true],
        ['handle' => 'subheading', 'display' => 'Subheading', 'type' => 'textarea', 'required' => false],
        ['handle' => 'content', 'display' => 'Content', 'type' => 'bard', 'required' => false],
    ]]]);
}

test('collections:create-homepage-entry creates a published, root homepage entry', function () {
    makeHomepageEntryPrereqs();

    $this->artisan('collections:create-homepage-entry')->assertSuccessful();

    $entry = Entry::where('collection', 'pages')->sole();

    expect($entry->blueprint)->toBe('homepage');
    expect($entry->status)->toBe('published');
    expect($entry->order)->toBe(1);
    expect($entry->data['heading'])->not->toBeEmpty();
    expect($entry->uri)->toBe('');

    $this->get('/')->assertOk()->assertSee($entry->data['heading']);
});

test('collections:create-homepage-entry skips if a homepage entry already exists', function () {
    makeHomepageEntryPrereqs();

    $this->artisan('collections:create-homepage-entry')->assertSuccessful();
    $this->artisan('collections:create-homepage-entry')->assertSuccessful();

    expect(Entry::where('collection', 'pages')->count())->toBe(1);
});

test('collections:create-homepage-entry is a no-op when no site exists yet', function () {
    $this->artisan('collections:create-homepage-entry')->assertSuccessful();

    expect(Entry::count())->toBe(0);
});

test('collections:create-homepage-entry is a no-op when the pages collection does not exist yet', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://example.id', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 1]);

    $this->artisan('collections:create-homepage-entry')->assertSuccessful();

    expect(Entry::count())->toBe(0);
});

test('collections:create-homepage-entry is a no-op when the homepage blueprint does not exist yet', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://example.id', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 1]);
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => ['route_mode' => 'single', 'route' => '{parent_uri}/{slug}']]);

    $this->artisan('collections:create-homepage-entry')->assertSuccessful();

    expect(Entry::count())->toBe(0);
});
