<?php

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Globals\Models\GlobalSet;
use App\Modules\Globals\Models\GlobalSetVariable;
use App\Modules\Sites\Models\Site;
use App\Support\Templating\TemplateTags;
use Illuminate\Support\Facades\Blade;

test('@global directive exposes a global set\'s current data', function () {
    GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => []]);
    GlobalSetVariable::create(['handle' => 'site_settings', 'locale' => null, 'data' => ['site_name' => 'Draft CMS']]);

    $html = Blade::render("@global('site_settings'){{ \$global->site_name }}@endglobal");

    expect($html)->toContain('Draft CMS');
});

test('@global directive renders nothing for a global set with no saved data', function () {
    $html = Blade::render("@global('missing')not found @endglobal");

    expect($html)->not->toContain('not found');
});

test('@collection directive loops only published entries, scoped to a site', function () {
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Site::create(['name' => 'Indonesia', 'handle' => 'indonesia', 'url' => '/', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 1]);
    Site::create(['name' => 'English', 'handle' => 'english', 'url' => '/en', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 2]);

    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'Halo Dunia']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'draft', 'data' => ['title' => 'Belum Terbit']]);
    Entry::create(['site' => 'english', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'Hello World']]);

    $html = Blade::render("@collection('blog', ['site' => 'indonesia']){{ \$data->title }} @endcollection");

    expect($html)
        ->toContain('Halo Dunia')
        ->not->toContain('Belum Terbit')
        ->not->toContain('Hello World');
});

test('@taxonomy directive loops a taxonomy\'s terms', function () {
    Taxonomy::create(['handle' => 'categories', 'title' => 'Categories', 'sites' => ['indonesia'], 'settings' => []]);

    TaxonomyTerm::create(['site' => 'indonesia', 'slug' => 'news', 'taxonomy' => 'categories', 'data' => ['title' => 'News']]);
    TaxonomyTerm::create(['site' => 'indonesia', 'slug' => 'sports', 'taxonomy' => 'categories', 'data' => ['title' => 'Sports']]);

    $html = Blade::render("@taxonomy('categories'){{ \$data->title }} @endtaxonomy");

    expect($html)->toContain('News')->toContain('Sports');
});

test('@sites directive loops every configured site, in order', function () {
    Site::create(['name' => 'English', 'handle' => 'english', 'url' => '/en', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 2]);
    Site::create(['name' => 'Indonesia', 'handle' => 'indonesia', 'url' => '/', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 1]);

    $html = Blade::render('@sites(){{ $site->handle }} @endsites');

    expect($html)->toBe('indonesia english ');
});

test('collection() limit caps how many entries come back', function () {
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'order' => 1, 'data' => ['title' => 'One']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'order' => 2, 'data' => ['title' => 'Two']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'order' => 3, 'data' => ['title' => 'Three']]);

    $entries = TemplateTags::collection('blog', ['limit' => 2]);

    expect($entries)->toHaveCount(2);
});

test('collection() status option overrides the "published" default', function () {
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'Published']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'draft', 'data' => ['title' => 'Draft']]);

    $entries = TemplateTags::collection('blog', ['status' => 'draft']);

    expect($entries)->toHaveCount(1)->and($entries->first()->data['title'])->toBe('Draft');
});

test('collection() where filters entries by a data field\'s value', function () {
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'Featured', 'featured' => true]]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'Regular', 'featured' => false]]);

    $entries = TemplateTags::collection('blog', ['where' => ['featured' => true]]);

    expect($entries)->toHaveCount(1)->and($entries->first()->data['title'])->toBe('Featured');
});

test('collection() sort orders by a data field, ascending or descending', function () {
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'B']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'A']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'C']]);

    $ascending = TemplateTags::collection('blog', ['sort' => 'title:asc'])->pluck('data.title')->all();
    $descending = TemplateTags::collection('blog', ['sort' => 'title:desc'])->pluck('data.title')->all();

    expect($ascending)->toBe(['A', 'B', 'C'])->and($descending)->toBe(['C', 'B', 'A']);
});

test('collection() random returns every entry, order aside', function () {
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'One']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'Two']]);

    $entries = TemplateTags::collection('blog', ['random' => true]);

    expect($entries)->toHaveCount(2);
});

test('collection() related returns entries sharing a taxonomy term, excluding the entry itself', function () {
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Taxonomy::create(['handle' => 'categories', 'title' => 'Categories', 'sites' => ['indonesia'], 'settings' => []]);
    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => [
        ['handle' => 'categories', 'display' => 'Categories', 'type' => 'taxonomy', 'required' => false, 'placeholder' => null, 'default' => null, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => null, 'options' => 'categories', 'min_items' => null, 'max_items' => null],
    ]]]);

    $news = TaxonomyTerm::create(['site' => 'indonesia', 'slug' => 'news', 'taxonomy' => 'categories', 'data' => ['title' => 'News']]);
    $sports = TaxonomyTerm::create(['site' => 'indonesia', 'slug' => 'sports', 'taxonomy' => 'categories', 'data' => ['title' => 'Sports']]);

    $source = Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'blueprint' => 'post', 'status' => 'published', 'data' => ['title' => 'Source', 'categories' => [$news->id]]]);
    $sameCategory = Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'blueprint' => 'post', 'status' => 'published', 'data' => ['title' => 'Same category', 'categories' => [$news->id]]]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'blueprint' => 'post', 'status' => 'published', 'data' => ['title' => 'Different category', 'categories' => [$sports->id]]]);

    $related = TemplateTags::collection('blog', ['related' => $source]);

    expect($related)->toHaveCount(1)->and($related->first()->id)->toBe($sameCategory->id);
});

test('collection() related returns nothing, not every entry, when the source has no taxonomy terms to relate on', function () {
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    $source = Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'Source']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'data' => ['title' => 'Other']]);

    $related = TemplateTags::collection('blog', ['related' => $source]);

    expect($related)->toBeEmpty();
});
