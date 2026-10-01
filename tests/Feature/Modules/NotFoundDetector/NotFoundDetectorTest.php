<?php

use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\NotFoundDetector\Livewire\NotFoundDetector;
use App\Modules\NotFoundDetector\Services\NotFoundScanner;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

function makeNotFoundDetectorTemplate(string $name): string
{
    $path = resource_path("views/pages/dynamic/{$name}.blade.php");
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<p>{{ $data[\'title\'] ?? \'\' }}</p>');

    return "pages.dynamic.{$name}";
}

function forgetNotFoundDetectorTemplate(string $name): void
{
    File::delete(resource_path("views/pages/dynamic/{$name}.blade.php"));
}

test('a healthy published entry is not reported as broken', function () {
    $template = makeNotFoundDetectorTemplate('nfd_test_healthy');

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => []]]);
        $collection = Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'healthy', 'uri' => 'healthy', 'data' => ['title' => 'Healthy'],
        ]);

        $results = app(NotFoundScanner::class)->scan([$collection->handle]);

        expect($results)->toHaveCount(0);
    } finally {
        forgetNotFoundDetectorTemplate('nfd_test_healthy');
    }
});

test('a published entry with no route configured is reported as broken', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => []]]);
    $collection = Collection::create(['title' => 'Pages', 'handle' => 'pages']);

    Entry::create([
        'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
        'slug' => 'no-route', 'data' => ['title' => 'No Route'],
    ]);

    $results = app(NotFoundScanner::class)->scan([$collection->handle]);

    expect($results)->toHaveCount(1);
    expect($results->first()['reason'])->toContain('no route configured');
});

test('a published entry whose blueprint has no valid template is reported as broken', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => []]]);
    $collection = Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

    Entry::create([
        'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
        'slug' => 'no-template', 'uri' => 'no-template', 'data' => ['title' => 'No Template'],
    ]);

    $results = app(NotFoundScanner::class)->scan([$collection->handle]);

    expect($results)->toHaveCount(1);
    expect($results->first()['reason'])->toContain('404');
});

test('draft entries are never reported, since a 404 is expected for them', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => []]]);
    $collection = Collection::create(['title' => 'Pages', 'handle' => 'pages']);

    Entry::create([
        'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'draft',
        'slug' => 'draft-page', 'data' => ['title' => 'Draft Page'],
    ]);

    $results = app(NotFoundScanner::class)->scan([$collection->handle]);

    expect($results)->toHaveCount(0);
});

test('scanning only the selected collections skips entries from other collections', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => []]]);
    Collection::create(['title' => 'Pages', 'handle' => 'pages']);
    Collection::create(['title' => 'Blog', 'handle' => 'blog']);

    Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published', 'slug' => 'a', 'data' => ['title' => 'A']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'blueprint' => 'page', 'status' => 'published', 'slug' => 'b', 'data' => ['title' => 'B']]);

    $results = app(NotFoundScanner::class)->scan(['pages']);

    expect($results)->toHaveCount(1);
    expect($results->first()['entry']->collection)->toBe('pages');
});

test('the 404 detector page can be scanned from the admin UI', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => []]]);
    Collection::create(['title' => 'Pages', 'handle' => 'pages']);

    Entry::create([
        'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
        'slug' => 'broken', 'data' => ['title' => 'Broken Page'],
    ]);

    Livewire::test(NotFoundDetector::class)
        ->call('scan')
        ->assertSet('hasScanned', true)
        ->assertSee('Broken Page');
});

test('the 404 detector page reports no broken links when everything resolves', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(NotFoundDetector::class)
        ->call('scan')
        ->assertSet('results', []);
});
