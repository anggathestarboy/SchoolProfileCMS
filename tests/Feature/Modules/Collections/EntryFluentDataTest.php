<?php

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Sites\Models\Site;

function makeFluentDataAsset(string $basename = 'photo.webp'): AssetMeta
{
    Collection::firstOrCreate(['handle' => 'pages'], ['title' => 'Pages', 'settings' => []]);
    AssetContainer::firstOrCreate(['handle' => 'assets'], ['title' => 'Assets', 'disk' => 'public']);

    return AssetMeta::create([
        'container' => 'assets', 'folder' => '', 'basename' => $basename, 'filename' => pathinfo($basename, PATHINFO_FILENAME),
        'extension' => 'webp', 'path' => $basename, 'meta' => ['mime_type' => 'image/webp'],
    ]);
}

test('a single-file assets field resolves to the asset itself, so $data->field->url works directly', function () {
    $asset = makeFluentDataAsset();
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'pilih_gambar', 'display' => 'Pilih Gambar', 'type' => 'assets', 'options' => 'assets', 'max_items' => 1],
    ]]]);
    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'pilih_gambar' => [$asset->id]]]);

    $resolved = $entry->fluentData()->pilih_gambar;

    expect($resolved)->toBeInstanceOf(AssetMeta::class);
    expect($resolved->url)->toBe($asset->url);
});

test('a single-file assets field with nothing selected resolves to null', function () {
    Collection::firstOrCreate(['handle' => 'pages'], ['title' => 'Pages', 'settings' => []]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'pilih_gambar', 'display' => 'Pilih Gambar', 'type' => 'assets', 'options' => 'assets', 'max_items' => 1],
    ]]]);
    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'pilih_gambar' => []]]);

    expect($entry->fluentData()->pilih_gambar)->toBeNull();
});

test('a multi-file assets field resolves to a list of assets in their stored order', function () {
    $first = makeFluentDataAsset('one.webp');
    $second = makeFluentDataAsset('two.webp');
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'gallery', 'display' => 'Gallery', 'type' => 'assets', 'options' => 'assets'],
    ]]]);
    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'gallery' => [$second->id, $first->id]]]);

    $resolved = $entry->fluentData()->gallery;

    expect($resolved)->toBeArray();
    expect(collect($resolved)->pluck('id')->all())->toBe([$second->id, $first->id]);
    expect($resolved[0]->url)->toBe($second->url);
});

test('a stale asset id (deleted from the media library) is silently dropped', function () {
    $asset = makeFluentDataAsset();
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'pilih_gambar', 'display' => 'Pilih Gambar', 'type' => 'assets', 'options' => 'assets', 'max_items' => 1],
    ]]]);
    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'pilih_gambar' => [$asset->id]]]);
    $asset->delete();

    expect($entry->fluentData()->pilih_gambar)->toBeNull();
});

test('an assets field nested inside a replicator row is resolved too', function () {
    $asset = makeFluentDataAsset();
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'blocks', 'display' => 'Blocks', 'type' => 'replicator', 'replicator_fields' => [
            ['handle' => 'icon', 'display' => 'Icon', 'type' => 'assets', 'options' => 'assets', 'max_items' => 1],
        ]],
    ]]]);
    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => [
        'title' => 'Home', 'blocks' => [['icon' => [$asset->id]]],
    ]]);

    $resolved = $entry->fluentData()->blocks[0]->icon;

    expect($resolved)->toBeInstanceOf(AssetMeta::class);
    expect($resolved->url)->toBe($asset->url);
});

test('a non-localizable assets field on a translation resolves from the origin entry\'s current value, even if the translation itself was never filled in', function () {
    $asset = makeFluentDataAsset();
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'pilih_gambar', 'display' => 'Pilih Gambar', 'type' => 'assets', 'options' => 'assets', 'max_items' => 1, 'localizable' => false],
    ]]]);
    $origin = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'pilih_gambar' => [$asset->id]]]);
    $translation = Entry::create(['site' => 'english', 'collection' => 'pages', 'blueprint' => 'page', 'origin_id' => $origin->id, 'data' => ['title' => 'Home', 'pilih_gambar' => []]]);

    $resolved = $translation->fluentData()->pilih_gambar;

    expect($resolved)->toBeInstanceOf(AssetMeta::class);
    expect($resolved->url)->toBe($asset->url);
});

test('a non-localizable field reflects the origin entry\'s latest value, not whatever the translation itself last had saved', function () {
    Collection::firstOrCreate(['handle' => 'pages'], ['title' => 'Pages', 'settings' => []]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'contentss', 'display' => 'Contentss', 'type' => 'textarea', 'localizable' => false],
    ]]]);
    $origin = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'contentss' => 'Original body']]);
    $translation = Entry::create(['site' => 'english', 'collection' => 'pages', 'blueprint' => 'page', 'origin_id' => $origin->id, 'data' => ['title' => 'Home', 'contentss' => 'Stale english body']]);

    // Origin was edited after the translation was last saved.
    $origin->update(['data' => ['title' => 'Home', 'contentss' => 'Updated body']]);

    expect($translation->fluentData()->contentss)->toBe('Updated body');
});

test('a localizable field on a translation keeps its own value instead of following the origin', function () {
    Collection::firstOrCreate(['handle' => 'pages'], ['title' => 'Pages', 'settings' => []]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'localizable' => true],
    ]]]);
    $origin = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'headline' => 'Selamat datang']]);
    $translation = Entry::create(['site' => 'english', 'collection' => 'pages', 'blueprint' => 'page', 'origin_id' => $origin->id, 'data' => ['title' => 'Home', 'headline' => 'Welcome']]);

    expect($translation->fluentData()->headline)->toBe('Welcome');
});

test('an origin entry (no origin_id) is unaffected by the non-localizable overlay', function () {
    Collection::firstOrCreate(['handle' => 'pages'], ['title' => 'Pages', 'settings' => []]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'contentss', 'display' => 'Contentss', 'type' => 'textarea', 'localizable' => false],
    ]]]);
    $origin = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'contentss' => 'Original body']]);

    expect($origin->fluentData()->contentss)->toBe('Original body');
});

test('a replicator row disabled in the editor is dropped from the frontend data entirely', function () {
    Collection::firstOrCreate(['handle' => 'pages'], ['title' => 'Pages', 'settings' => []]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'blocks', 'display' => 'Blocks', 'type' => 'replicator', 'replicator_fields' => [
            ['handle' => 'label', 'display' => 'Label', 'type' => 'text'],
        ]],
    ]]]);
    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => [
        'title' => 'Home',
        'blocks' => [
            ['_enabled' => true, 'label' => 'Visible'],
            ['_enabled' => false, 'label' => 'Hidden'],
        ],
    ]]);

    $resolved = $entry->fluentData()->blocks;

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]->label)->toBe('Visible');
    expect(property_exists($resolved[0], '_enabled'))->toBeFalse();
});

test('a replicator row with no "_enabled" key at all (created before the toggle existed) is treated as enabled', function () {
    Collection::firstOrCreate(['handle' => 'pages'], ['title' => 'Pages', 'settings' => []]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'blocks', 'display' => 'Blocks', 'type' => 'replicator', 'replicator_fields' => [
            ['handle' => 'label', 'display' => 'Label', 'type' => 'text'],
        ]],
    ]]]);
    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => [
        'title' => 'Home',
        'blocks' => [['label' => 'Legacy row']],
    ]]);

    $resolved = $entry->fluentData()->blocks;

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]->label)->toBe('Legacy row');
});

test('a disabled row nested inside another replicator is dropped too', function () {
    Collection::firstOrCreate(['handle' => 'pages'], ['title' => 'Pages', 'settings' => []]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'sections', 'display' => 'Sections', 'type' => 'replicator', 'replicator_fields' => [
            ['handle' => 'items', 'display' => 'Items', 'type' => 'replicator', 'replicator_fields' => [
                ['handle' => 'label', 'display' => 'Label', 'type' => 'text'],
            ]],
        ]],
    ]]]);
    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'data' => [
        'title' => 'Home',
        'sections' => [
            ['_enabled' => true, 'items' => [
                ['_enabled' => true, 'label' => 'Kept'],
                ['_enabled' => false, 'label' => 'Dropped'],
            ]],
        ],
    ]]);

    $resolved = $entry->fluentData()->sections[0]->items;

    expect($resolved)->toHaveCount(1);
    expect($resolved[0]->label)->toBe('Kept');
});

test('a link to the default language\'s entry resolves to that entry\'s translation on this site, and to "#" while that translation is unpublished', function () {
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://id.local', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'http://en.local', 'locale' => 'en_GB', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'options' => []],
    ]]]);

    $indonesia = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'published' => true, 'slug' => 'berita', 'uri' => 'berita', 'data' => ['title' => 'Berita ID']]);
    $english = Entry::create(['site' => 'english', 'collection' => 'pages', 'blueprint' => 'page', 'origin_id' => $indonesia->id, 'published' => false, 'slug' => 'news', 'uri' => 'news', 'data' => ['title' => 'News EN']]);

    $entry = Entry::create(['site' => 'english', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'cta' => "entry::{$indonesia->id}"]]);

    expect($entry->fluentData()->cta)->toBe('#');

    $english->update(['published' => true, 'status' => 'published']);

    expect($entry->fresh()->fluentData()->cta)->toBe('http://en.local/news');
});

test('a link pointing at an entry with no translation on this site resolves to "#"', function () {
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://id.local', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'http://en.local', 'locale' => 'en_GB', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => [
        ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'options' => []],
    ]]]);

    $indonesia = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'published' => true, 'slug' => 'berita', 'uri' => 'berita', 'data' => ['title' => 'Berita ID']]);

    $entry = Entry::create(['site' => 'english', 'collection' => 'pages', 'blueprint' => 'page', 'data' => ['title' => 'Home', 'cta' => "entry::{$indonesia->id}"]]);

    expect($entry->fluentData()->cta)->toBe('#');
});
