<?php

use App\Modules\Collections\Models\Collection;

test('collections:create-pages creates the default Pages collection with structured, orderable, live preview, schema, and SEO all enabled', function () {
    $this->artisan('collections:create-pages')->assertSuccessful();

    $collection = Collection::where('handle', 'pages')->sole();

    expect($collection->title)->toBe('Pages');
    expect($collection->structured())->toBeTrue();
    expect($collection->orderable())->toBeTrue();
    expect($collection->livePreview())->toBeTrue();
    expect($collection->schemaEnabled())->toBeTrue();
    expect($collection->seoEnabled())->toBeTrue();
    expect($collection->expectsRoot())->toBeTrue();
    expect($collection->routeForSite('any'))->toBe('{parent_uri}/{slug}');
});

test('collections:create-pages skips if a pages collection already exists', function () {
    Collection::create(['handle' => 'pages', 'title' => 'Existing Pages', 'settings' => []]);

    $this->artisan('collections:create-pages')->assertSuccessful();

    expect(Collection::where('handle', 'pages')->count())->toBe(1);
    expect(Collection::where('handle', 'pages')->sole()->title)->toBe('Existing Pages');
});
