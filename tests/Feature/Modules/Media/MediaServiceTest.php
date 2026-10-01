<?php

// Tests intentionally left empty pending an update for the per-container
// scoped storage layout (containers now root at their own handle folder).

use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use Illuminate\Support\Facades\Storage;

test('title and alt default to a humanized filename until explicitly set', function () {
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);

    $asset = AssetMeta::create([
        'container' => 'photos',
        'folder' => '',
        'basename' => 'summer-sale_banner.jpg',
        'filename' => 'summer-sale_banner',
        'extension' => 'jpg',
        'path' => 'summer-sale_banner.jpg',
        'meta' => ['size' => 123, 'mime_type' => 'image/jpeg'],
    ]);

    expect($asset->title)->toBe('Summer Sale Banner');
    expect($asset->alt)->toBe('Summer Sale Banner');

    $asset->update(['meta' => array_merge($asset->meta, ['data' => ['title' => 'Custom Title', 'alt' => 'Custom Alt']])]);
    $asset->refresh();

    expect($asset->title)->toBe('Custom Title');
    expect($asset->alt)->toBe('Custom Alt');
});

test('an asset on a private ("local") disk container is actually reachable at its own url', function () {
    Storage::fake('local');
    AssetContainer::create(['handle' => 'cv', 'title' => 'CV', 'disk' => 'local']);
    Storage::disk('local')->put('cv/report.pdf', 'contents');
    $asset = AssetMeta::create([
        'container' => 'cv',
        'folder' => '',
        'basename' => 'report.pdf',
        'filename' => 'report',
        'extension' => 'pdf',
        'path' => 'report.pdf',
        'meta' => ['size' => 8, 'mime_type' => 'application/pdf'],
    ]);

    // The "/{uri}" catch-all page route (routes/web.php) must not swallow
    // "storage/..." — otherwise it wins over Laravel's own signed storage.local
    // route (registered later, once every provider has booted) and every
    // private-disk asset 404s instead of being served.
    $this->get($asset->url)->assertOk();
});
