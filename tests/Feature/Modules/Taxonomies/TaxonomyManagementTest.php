<?php

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Sites\Models\Site;
use App\Modules\Taxonomies\Livewire\TaxonomyManager;
use App\Modules\Taxonomies\Livewire\TermForm;
use App\Modules\Taxonomies\Livewire\TermManager;
use App\Modules\Users\Models\Role;
use Livewire\Livewire;

function makeTaxonomySites(): void
{
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://example.id', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 1]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 2]);
}

function makeNewsCategoriesBlueprint(): Blueprint
{
    return Blueprint::create([
        'namespace' => null,
        'handle' => 'news_categories',
        'data' => ['fields' => [
            ['handle' => 'color', 'display' => 'Color', 'type' => 'text', 'required' => false, 'localizable' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
            ['handle' => 'description', 'display' => 'Description', 'type' => 'textarea', 'required' => false, 'localizable' => true, 'placeholder' => null, 'default' => null, 'instructions' => null],
        ]],
    ]);
}

test('taxonomy can be created with a handle-generate button and locked default language', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();
    $blueprint = makeNewsCategoriesBlueprint();

    Livewire::test(TaxonomyManager::class)
        ->call('create')
        ->assertSet('sites', ['indonesia'])
        ->assertSet('createBlueprint', true)
        ->set('title', 'News Categories')
        ->call('regenerateHandle')
        ->assertSet('handle', 'news_categories')
        ->set('createBlueprint', false)
        ->set('blueprint', $blueprint->handle)
        ->set('sites', ['english'])
        ->call('save')
        ->assertHasNoErrors();

    $taxonomy = Taxonomy::where('handle', 'news_categories')->sole();

    expect($taxonomy->title)->toBe('News Categories');
    expect($taxonomy->sites)->toBe(['indonesia', 'english']);
    expect($taxonomy->effectiveBlueprint()->handle)->toBe('news_categories');
});

test('taxonomy creation defaults to auto-creating its own blueprint, namespaced to the taxonomy', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(TaxonomyManager::class)
        ->set('title', 'News Categories')
        ->set('handle', 'news_categories')
        ->call('save')
        ->assertHasNoErrors();

    $taxonomy = Taxonomy::sole();

    expect($taxonomy->settings['blueprint'])->toBeNull();

    $blueprint = Blueprint::where('namespace', 'taxonomies.news_categories')->sole();

    expect($blueprint->handle)->toBe('news_categories');
    expect($blueprint->data['fields'])->toBe([]);
    expect($taxonomy->effectiveBlueprint()->id)->toBe($blueprint->id);
});

test('editing a taxonomy pre-selects its blueprint and keeps the default language locked', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();
    $blueprint = makeNewsCategoriesBlueprint();

    $taxonomy = Taxonomy::create([
        'handle' => 'news_categories',
        'title' => 'News Categories',
        'sites' => ['indonesia'],
        'settings' => ['blueprint' => $blueprint->handle],
    ]);

    Livewire::test(TaxonomyManager::class)
        ->call('edit', $taxonomy->id)
        ->assertSet('blueprint', 'news_categories')
        ->set('sites', [])
        ->call('save')
        ->assertHasNoErrors();

    expect($taxonomy->fresh()->sites)->toBe(['indonesia']);
});

test('terms page is displayed for a taxonomy', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    $this->get("/admin-panel/taxonomies/{$taxonomy->handle}")->assertOk();
});

test('a term can be created and appears in the term manager', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])
        ->assertSet('site', 'indonesia')
        ->set('title', 'Politics')
        ->set('slug', 'politics')
        ->set('data.color', 'red')
        ->set('data.description', 'Political news')
        ->call('save')
        ->assertHasNoErrors();

    $term = TaxonomyTerm::where('taxonomy', 'news_categories')->sole();

    expect($term->data)->toBe(['color' => 'red', 'description' => 'Political news', 'title' => 'Politics']);
    expect($term->site)->toBe('indonesia');

    Livewire::test(TermManager::class, ['taxonomy' => $taxonomy])
        ->assertSee('Politics');
});

test('a term requires a slug', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])
        ->set('title', 'Politics')
        ->set('slug', '')
        ->set('data.color', 'red')
        ->set('data.description', 'Political news')
        ->call('save')
        ->assertHasErrors(['slug' => 'required']);
});

test('a term\'s slug can be regenerated from the title', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])
        ->set('title', 'World Politics, Again!')
        ->set('slug', 'something-else')
        ->call('regenerateSlug')
        ->assertSet('slug', 'world-politics-again');
});

test('a required blueprint field on a term shows its display label, not the raw data key, in the error message', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();

    Blueprint::create(['namespace' => null, 'handle' => 'news_categories', 'data' => ['fields' => [
        ['handle' => 'seo_keyword', 'display' => 'SEO Keyword', 'type' => 'text', 'required' => true, 'localizable' => true, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])
        ->set('title', 'Politics')
        ->set('slug', 'politics')
        ->call('save')
        ->assertHasErrors(['data.seo_keyword'])
        ->assertSee('The SEO Keyword field is required.');
});

test('non-localizable term fields share one value from the taxonomy default site across all languages', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    $original = TaxonomyTerm::create([
        'taxonomy' => 'news_categories',
        'site' => 'indonesia',
        'blueprint' => 'news_categories',
        'slug' => 'politik',
        'data' => ['title' => 'Politik', 'color' => 'red', 'description' => 'Berita politik'],
    ]);

    $translation = TaxonomyTerm::create([
        'taxonomy' => 'news_categories',
        'site' => 'english',
        'origin_id' => $original->id,
        'blueprint' => 'news_categories',
        'slug' => 'politics',
        'data' => ['title' => 'Politics', 'color' => 'should-be-overwritten', 'description' => 'Political news'],
    ]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'term' => $translation])
        ->assertSet('data.color', 'red')
        ->assertSet('data.description', 'Political news')
        ->set('data.color', 'blue')
        ->set('data.description', 'Updated political news')
        ->call('save')
        ->assertHasNoErrors();

    $translation->refresh();

    expect($translation->data['color'])->toBe('red');
    expect($translation->data['description'])->toBe('Updated political news');
    expect($original->fresh()->data['color'])->toBe('red');
});

test('link fields on a term only offer entries of the term\'s language', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => []]);

    Blueprint::create(['namespace' => null, 'handle' => 'news_categories', 'data' => ['fields' => [
        ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'options' => []],
    ]]]);
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    $indonesia = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'data' => ['title' => 'Beranda']]);
    $english = Entry::create(['site' => 'english', 'collection' => 'pages', 'data' => ['title' => 'Home']]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])
        ->call('linkableEntries', [])
        ->assertReturned(fn ($entries) => array_column($entries, 'id') === [$indonesia->id]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'english'])
        ->call('linkableEntries', [])
        ->assertReturned(fn ($entries) => array_column($entries, 'id') === [$english->id]);
});

test('a blueprint tab with handle "sidebar" renders its fields in the term form\'s right-hand sidebar column', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();

    Blueprint::create(['namespace' => null, 'handle' => 'news_categories', 'data' => [
        'tabs' => [
            ['handle' => 'main', 'display' => 'Main'],
            ['handle' => 'sidebar', 'display' => 'Sidebar'],
        ],
        'fields' => [
            ['handle' => 'color', 'display' => 'Color', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => null, 'tab' => 'main'],
            ['handle' => 'seo_title', 'display' => 'Seo Title', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => null, 'tab' => 'sidebar'],
        ],
    ]]);
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])
        ->assertSee('Color')
        ->assertSee('Seo Title');
});

test('a term field with a "show when" condition only renders once it matches', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();

    Blueprint::create(['namespace' => null, 'handle' => 'news_categories', 'data' => ['fields' => [
        ['handle' => 'is_featured', 'display' => 'Is Featured', 'type' => 'toggle', 'required' => false, 'placeholder' => null, 'default' => false, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => null],
        ['handle' => 'banner_text', 'display' => 'Banner Text', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => ['mode' => 'show', 'field' => 'is_featured', 'operator' => 'equals', 'value' => 'true']],
    ]]]);
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])
        ->assertDontSee('Banner Text')
        ->set('data.is_featured', true)
        ->assertSee('Banner Text');
});

test('a term field referenced by another field\'s condition is bound live', function () {
    $this->actingAs(User::factory()->create());
    makeTaxonomySites();

    Blueprint::create(['namespace' => null, 'handle' => 'news_categories', 'data' => ['fields' => [
        ['handle' => 'is_featured', 'display' => 'Is Featured', 'type' => 'toggle', 'required' => false, 'placeholder' => null, 'default' => false, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => null],
        ['handle' => 'banner_text', 'display' => 'Banner Text', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => ['mode' => 'show', 'field' => 'is_featured', 'operator' => 'equals', 'value' => 'true']],
    ]]]);
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia', 'english'], 'settings' => ['blueprint' => 'news_categories']]);

    $html = Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])->html();

    expect($html)->toContain('wire:model.live="data.is_featured"');
});

test('creating a taxonomy requires the configure taxonomies permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TaxonomyManager::class)->call('create')->assertStatus(403);
});

test('a user with configure taxonomies permission can create a taxonomy', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure taxonomies']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TaxonomyManager::class)
        ->call('create')
        ->set('title', 'News Categories')
        ->set('handle', 'news_categories')
        ->call('save')
        ->assertHasNoErrors();

    expect(Taxonomy::where('handle', 'news_categories')->exists())->toBeTrue();
});

test('editing a taxonomy requires the configure taxonomies permission', function () {
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => [], 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TaxonomyManager::class)->call('edit', $taxonomy->id)->assertStatus(403);
});

test('a user with configure taxonomies permission can edit a taxonomy', function () {
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => [], 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure taxonomies']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TaxonomyManager::class)
        ->call('edit', $taxonomy->id)
        ->assertSet('handle', 'news_categories');
});

test('saving a taxonomy requires the configure taxonomies permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TaxonomyManager::class)
        ->set('title', 'News Categories')
        ->set('handle', 'news_categories')
        ->call('save')->assertStatus(403);
});

test('deleting a taxonomy requires the configure taxonomies permission', function () {
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => [], 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TaxonomyManager::class)->call('delete', $taxonomy->id)->assertStatus(403);
});

test('a user with configure taxonomies permission can delete a taxonomy', function () {
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => [], 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure taxonomies']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TaxonomyManager::class)->call('delete', $taxonomy->id);

    expect(Taxonomy::find($taxonomy->id))->toBeNull();
});

test('viewing the term manager requires the view terms permission', function () {
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => [], 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TermManager::class, ['taxonomy' => $taxonomy])->assertStatus(403);
});

test('a user with view terms permission can see the term manager', function () {
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => [], 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'view news_categories terms']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TermManager::class, ['taxonomy' => $taxonomy])
        ->assertOk();
});

test('deleting a term requires the delete terms permission', function () {
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia'], 'settings' => ['blueprint' => 'news_categories']]);
    $term = TaxonomyTerm::create(['taxonomy' => 'news_categories', 'site' => 'indonesia', 'blueprint' => 'news_categories', 'slug' => 'politics', 'data' => ['title' => 'Politics']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view news_categories terms']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TermManager::class, ['taxonomy' => $taxonomy])->call('delete', $term->id)->assertStatus(403);
});

test('a user with delete terms permission can delete a term', function () {
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia'], 'settings' => ['blueprint' => 'news_categories']]);
    $term = TaxonomyTerm::create(['taxonomy' => 'news_categories', 'site' => 'indonesia', 'blueprint' => 'news_categories', 'slug' => 'politics', 'data' => ['title' => 'Politics']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'view news_categories terms', 'delete news_categories terms']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TermManager::class, ['taxonomy' => $taxonomy])->call('delete', $term->id);

    expect(TaxonomyTerm::find($term->id))->toBeNull();
});

test('creating a term requires the create terms permission', function () {
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia'], 'settings' => ['blueprint' => 'news_categories']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])->assertStatus(403);
});

test('a user with create terms permission can create a term', function () {
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia'], 'settings' => ['blueprint' => 'news_categories']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'create news_categories terms']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'site' => 'indonesia'])
        ->set('title', 'Politics')
        ->set('slug', 'politics')
        ->call('save')
        ->assertHasNoErrors();

    expect(TaxonomyTerm::where('taxonomy', 'news_categories')->exists())->toBeTrue();
});

test('editing a term requires the edit terms permission', function () {
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia'], 'settings' => ['blueprint' => 'news_categories']]);
    $term = TaxonomyTerm::create(['taxonomy' => 'news_categories', 'site' => 'indonesia', 'blueprint' => 'news_categories', 'slug' => 'politics', 'data' => ['title' => 'Politics']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'term' => $term])->assertStatus(403);
});

test('a user with edit terms permission can edit a term', function () {
    makeTaxonomySites();
    makeNewsCategoriesBlueprint();
    $taxonomy = Taxonomy::create(['handle' => 'news_categories', 'title' => 'News Categories', 'sites' => ['indonesia'], 'settings' => ['blueprint' => 'news_categories']]);
    $term = TaxonomyTerm::create(['taxonomy' => 'news_categories', 'site' => 'indonesia', 'blueprint' => 'news_categories', 'slug' => 'politics', 'data' => ['title' => 'Politics']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'edit news_categories terms']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(TermForm::class, ['taxonomy' => $taxonomy, 'term' => $term])
        ->assertSet('slug', 'politics');
});
