<?php

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Livewire\EntryForm;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Collections\Services\EntryUrlResolver;
use App\Modules\Sites\Models\Site;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

function makeTemplate(string $name): string
{
    $path = resource_path("views/pages/dynamic/{$name}.blade.php");
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<p>{{ $data[\'title\'] ?? \'\' }}</p>');

    return "pages.dynamic.{$name}";
}

function forgetTemplate(string $name): void
{
    File::delete(resource_path("views/pages/dynamic/{$name}.blade.php"));
}

test('saving an entry persists its resolved uri', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://example.id', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => []]]);
    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => ['route_mode' => 'single', 'route' => 'blog/{slug}']]);

    Livewire::test(EntryForm::class, ['collection' => $collection, 'site' => 'indonesia'])
        ->set('title', 'Hello')
        ->set('slug', 'hello')
        ->set('entryDate', '2024-01-01T00:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Entry::sole()->uri)->toBe('blog/hello');
});

test('a published entry renders through its resolved public url', function () {
    $template = makeTemplate('page_controller_test_home');

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => []]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'homepage-halaman', 'uri' => 'homepage-halaman', 'data' => ['title' => 'Homepage'],
        ]);

        $this->get('/homepage-halaman')->assertOk()->assertSee('Homepage');
    } finally {
        forgetTemplate('page_controller_test_home');
    }
});

test('an entry without a stored uri is still found by live-resolving and self-heals', function () {
    $template = makeTemplate('page_controller_test_selfheal');

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => []]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        $entry = Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'about', 'uri' => null, 'data' => ['title' => 'About'],
        ]);

        $this->get('/about')->assertOk()->assertSee('About');

        expect($entry->fresh()->uri)->toBe('about');
    } finally {
        forgetTemplate('page_controller_test_selfheal');
    }
});

test('a draft entry is not publicly visitable', function () {
    $template = makeTemplate('page_controller_test_draft');

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => []]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'draft',
            'slug' => 'secret', 'uri' => 'secret', 'data' => ['title' => 'Secret'],
        ]);

        $this->get('/secret')->assertNotFound();
    } finally {
        forgetTemplate('page_controller_test_draft');
    }
});

test('an entry whose blueprint has no template renders as not found', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['fields' => []]]);
    Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

    Entry::create([
        'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
        'slug' => 'no-template', 'uri' => 'no-template', 'data' => ['title' => 'No Template'],
    ]);

    $this->get('/no-template')->assertNotFound();
});

test('an unmatched path is not found', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);

    $this->get('/does-not-exist')->assertNotFound();
});

test('a path-prefixed site (e.g. /en) resolves its own entries, not the root site\'s', function () {
    $indonesiaTemplate = makeTemplate('page_controller_test_id');
    $englishTemplate = makeTemplate('page_controller_test_en');

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => '/', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Site::create(['handle' => 'english', 'name' => 'English', 'url' => '/en', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $indonesiaTemplate, 'fields' => []]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'homepage-halaman', 'uri' => 'homepage-halaman', 'data' => ['title' => 'Homepage ID'],
        ]);
        Entry::create([
            'site' => 'english', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'halo-slug', 'uri' => 'halo-slug', 'data' => ['title' => 'Homepage EN'],
        ]);

        $this->get('/homepage-halaman')->assertOk()->assertSee('Homepage ID');
        $this->get('/en/halo-slug')->assertOk()->assertSee('Homepage EN');

        // Each site's entry only exists under its own prefix.
        $this->get('/halo-slug')->assertNotFound();
        $this->get('/en/homepage-halaman')->assertNotFound();
    } finally {
        forgetTemplate('page_controller_test_id');
        forgetTemplate('page_controller_test_en');
    }
});

test('an entry with order 1 is the homepage and its url has no slug', function () {
    $indonesiaTemplate = makeTemplate('page_controller_test_homepage_id');
    $englishTemplate = makeTemplate('page_controller_test_homepage_en');

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => '/', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Site::create(['handle' => 'english', 'name' => 'English', 'url' => '/en', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $indonesiaTemplate, 'fields' => []]]);
        $collection = Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        $idHome = Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published', 'order' => 1,
            'slug' => 'homepage-halaman', 'data' => ['title' => 'Homepage ID'],
        ]);
        $enHome = Entry::create([
            'site' => 'english', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published', 'order' => 1,
            'slug' => 'halo-slug', 'data' => ['title' => 'Homepage EN'],
        ]);

        expect(app(EntryUrlResolver::class)->url($collection, $idHome))->toBe(rtrim(config('app.url'), '/'));
        expect(app(EntryUrlResolver::class)->url($collection, $enHome))->toBe(rtrim(config('app.url'), '/').'/en');

        $this->get('/')->assertOk()->assertSee('Homepage ID');
        $this->get('/en')->assertOk()->assertSee('Homepage EN');

        // The old slug-based URLs no longer resolve for the homepage entry.
        $this->get('/homepage-halaman')->assertNotFound();
        $this->get('/en/halo-slug')->assertNotFound();
    } finally {
        forgetTemplate('page_controller_test_homepage_id');
        forgetTemplate('page_controller_test_homepage_en');
    }
});

test('the visit url for a path-prefixed site matches what the public page controller resolves', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => '/', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => '/en', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);
    Blueprint::create(['namespace' => 'collections.pages', 'handle' => 'page', 'data' => ['fields' => []]]);
    $collection = Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

    Livewire::test(EntryForm::class, ['collection' => $collection, 'site' => 'english'])
        ->set('title', 'Hello')
        ->set('slug', 'halo-slug')
        ->set('entryDate', '2024-01-01T00:00')
        ->call('save')
        ->assertHasNoErrors();

    $url = Livewire::test(EntryForm::class, ['collection' => $collection, 'entry' => Entry::sole()])
        ->instance()->resolvedUrl;

    expect($url)->toBe(rtrim(config('app.url'), '/').'/en/halo-slug');
});

test('a template can read entry data with arrow access, including nested replicator rows', function () {
    $path = resource_path('views/pages/dynamic/page_controller_test_fluent.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<p>{{ $data->title }}</p><ul>@foreach ($data->rows as $row)<li>{{ $row->label }}</li>@endforeach</ul>');
    $template = 'pages.dynamic.page_controller_test_fluent';

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => []]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'fluent-test', 'uri' => 'fluent-test',
            'data' => ['title' => 'Fluent Title', 'rows' => [['label' => 'First row'], ['label' => 'Second row']]],
        ]);

        $this->get('/fluent-test')->assertOk()->assertSee('Fluent Title')->assertSee('First row')->assertSee('Second row');
    } finally {
        File::delete($path);
    }
});

test('a single-select taxonomy field resolves to the term itself, not just its id', function () {
    $path = resource_path('views/pages/dynamic/page_controller_test_taxonomy_single.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<p>{{ $data->category->title }}</p>');
    $template = 'pages.dynamic.page_controller_test_taxonomy_single';

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Taxonomy::create(['handle' => 'categories', 'title' => 'Categories', 'sites' => [], 'settings' => []]);
        $term = TaxonomyTerm::create(['taxonomy' => 'categories', 'site' => 'indonesia', 'slug' => 'news', 'data' => ['title' => 'News']]);

        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => [
            ['handle' => 'category', 'display' => 'Category', 'type' => 'taxonomy', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'max_items' => 1, 'options' => 'categories'],
        ]]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'taxonomy-single-test', 'uri' => 'taxonomy-single-test',
            'data' => ['title' => 'Post', 'category' => [$term->id]],
        ]);

        $this->get('/taxonomy-single-test')->assertOk()->assertSee('News');
    } finally {
        File::delete($path);
    }
});

test('a multi-select taxonomy field resolves to a list of terms, in the stored order', function () {
    $path = resource_path('views/pages/dynamic/page_controller_test_taxonomy_multi.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<ul>@foreach ($data->categories as $category)<li>{{ $category->title }}</li>@endforeach</ul>');
    $template = 'pages.dynamic.page_controller_test_taxonomy_multi';

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Taxonomy::create(['handle' => 'categories', 'title' => 'Categories', 'sites' => [], 'settings' => []]);
        $second = TaxonomyTerm::create(['taxonomy' => 'categories', 'site' => 'indonesia', 'slug' => 'second', 'data' => ['title' => 'Second']]);
        $first = TaxonomyTerm::create(['taxonomy' => 'categories', 'site' => 'indonesia', 'slug' => 'first', 'data' => ['title' => 'First']]);

        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => [
            ['handle' => 'categories', 'display' => 'Categories', 'type' => 'taxonomy', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'max_items' => null, 'options' => 'categories'],
        ]]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'taxonomy-multi-test', 'uri' => 'taxonomy-multi-test',
            'data' => ['title' => 'Post', 'categories' => [$first->id, $second->id]],
        ]);

        $this->get('/taxonomy-multi-test')->assertOk()->assertSeeInOrder(['First', 'Second']);
    } finally {
        File::delete($path);
    }
});

test('a link field pointing at an entry resolves to that entry\'s public url', function () {
    $path = resource_path('views/pages/dynamic/page_controller_test_link.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<a href="{{ $data->button_url }}">Go</a>');
    $template = 'pages.dynamic.page_controller_test_link';

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => [
            ['handle' => 'button_url', 'display' => 'Button Url', 'type' => 'link', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'options' => []],
        ]]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        $target = Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'target-page', 'uri' => 'target-page',
            'data' => ['title' => 'Target'],
        ]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'link-test', 'uri' => 'link-test',
            'data' => ['title' => 'Post', 'button_url' => "entry::{$target->id}"],
        ]);

        $this->get('/link-test')->assertOk()->assertSee('http://127.0.0.1/target-page', false);
    } finally {
        File::delete($path);
    }
});

test('a link field nested inside a replicator row also resolves to the linked entry\'s public url', function () {
    $path = resource_path('views/pages/dynamic/page_controller_test_link_replicator.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '@foreach ($data->rows as $row)<a href="{{ $row->button_url }}">{{ $row->headers }}</a>@endforeach');
    $template = 'pages.dynamic.page_controller_test_link_replicator';

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => [
            ['handle' => 'rows', 'display' => 'Rows', 'type' => 'replicator', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'replicator_fields' => [
                ['handle' => 'headers', 'display' => 'Headers', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
                ['handle' => 'button_url', 'display' => 'Button Url', 'type' => 'link', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'options' => []],
            ]],
        ]]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        $target = Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'target-page', 'uri' => 'target-page',
            'data' => ['title' => 'Target'],
        ]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'link-replicator-test', 'uri' => 'link-replicator-test',
            'data' => ['title' => 'Post', 'rows' => [['headers' => 'Go', 'button_url' => "entry::{$target->id}"]]],
        ]);

        $this->get('/link-replicator-test')->assertOk()->assertSee('http://127.0.0.1/target-page', false);
    } finally {
        File::delete($path);
    }
});

test('an author field resolves to that user\'s name and email, not just the raw id', function () {
    $path = resource_path('views/pages/dynamic/page_controller_test_author.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<p>{{ $data->author->name }} ({{ $data->author->email }})</p>');
    $template = 'pages.dynamic.page_controller_test_author';

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        $author = User::factory()->create(['name' => 'Jane Editor', 'email' => 'jane@example.com']);

        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => [
            ['handle' => 'author', 'display' => 'Author', 'type' => 'author', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
        ]]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'author-test', 'uri' => 'author-test',
            'data' => ['title' => 'Post', 'author' => (string) $author->id],
        ]);

        $this->get('/author-test')->assertOk()->assertSee('Jane Editor')->assertSee('jane@example.com');
    } finally {
        File::delete($path);
    }
});
