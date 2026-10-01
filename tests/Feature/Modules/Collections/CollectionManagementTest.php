<?php

use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Livewire\CollectionManager;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Sites\Models\Site;
use App\Modules\Users\Models\Role;
use Livewire\Livewire;

test('collections page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin-panel/collections')->assertOk();
});

test('collection can be created', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->title)->toBe('Blog');
    expect($collection->handle)->toBe('blog');
    expect($collection->settings)->toBe([
        'route_mode' => 'single',
        'route' => null,
        'routes' => [],
        'sites' => [],
        'structured' => false,
        'orderable' => false,
        'visible_order' => true,
        'live_preview' => false,
        'schema_enabled' => false,
        'seo_enabled' => false,
        'max_depth' => null,
        'expects_root' => false,
        'save_action' => 'listing',
    ]);
});

test('collection blueprint is resolved from the blueprint\'s own namespace, not configured here', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => []]]);

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->call('save')
        ->assertHasNoErrors();

    expect(Collection::sole()->effectiveBlueprint()->handle)->toBe('post');
});

test('collection handle must be unique', function () {
    $this->actingAs(User::factory()->create());

    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog Two')
        ->set('handle', 'blog')
        ->call('save')
        ->assertHasErrors(['handle']);
});

test('collection route round-trips through edit', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://id.test', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);

    $collection = Collection::create([
        'title' => 'Blog',
        'handle' => 'blog',
        'settings' => ['route_mode' => 'single', 'route' => 'blog/{slug}'],
    ]);

    Livewire::test(CollectionManager::class)
        ->call('edit', $collection->id)
        ->assertSet('route', 'blog/{slug}')
        ->set('title', 'Articles')
        ->call('save')
        ->assertHasNoErrors();

    $collection->refresh();

    expect($collection->title)->toBe('Articles');
    expect($collection->settings['route'])->toBe('blog/{slug}');
});

test('collection sites can be restricted, and checking every site means unrestricted', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://id.test', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://en.test', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('sites', ['indonesia'])
        ->call('save')
        ->assertHasNoErrors();

    expect(Collection::sole()->configuredSites())->toBe(['indonesia']);

    $collection = Collection::first();

    Livewire::test(CollectionManager::class)
        ->call('edit', $collection->id)
        ->assertSet('sites', ['indonesia'])
        ->set('sites', ['indonesia', 'english'])
        ->call('save')
        ->assertHasNoErrors();

    expect($collection->refresh()->configuredSites())->toBe([]);
});

test('collection sites must reference an existing site handle', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('sites', ['does-not-exist'])
        ->call('save')
        ->assertHasErrors(['sites.0']);
});

test('the default site cannot be unchecked from a collection', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://id.test', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://en.test', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    Livewire::test(CollectionManager::class)
        ->call('create')
        ->set('sites', ['english'])
        ->assertSet('sites', fn ($sites) => in_array('indonesia', $sites, true));
});

test('collection routes can be configured per site', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://id.test', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://en.test', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('sites', ['indonesia', 'english'])
        ->set('routeMode', 'per_site')
        ->set('routes', ['indonesia' => 'blog/{slug}', 'english' => 'en/blog/{slug}'])
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->routeMode())->toBe('per_site');
    expect($collection->routes())->toBe(['indonesia' => 'blog/{slug}', 'english' => 'en/blog/{slug}']);
    expect($collection->routeForSite('indonesia'))->toBe('blog/{slug}');
    expect($collection->routeForSite('english'))->toBe('en/blog/{slug}');
});

test('a route for a site that got unchecked is dropped on save', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://id.test', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://en.test', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('sites', ['indonesia'])
        ->set('routeMode', 'per_site')
        ->set('routes', ['indonesia' => 'blog/{slug}', 'english' => 'en/blog/{slug}'])
        ->call('save')
        ->assertHasNoErrors();

    // Only one site is checked, so per-site collapses back to a single route.
    $collection = Collection::sole();
    expect($collection->routeMode())->toBe('single');
    expect($collection->routes())->toBe([]);
});

test('single route applies to every checked site', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://id.test', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://en.test', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('sites', ['indonesia', 'english'])
        ->set('route', 'blog/{slug}')
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->routeMode())->toBe('single');
    expect($collection->routeForSite('indonesia'))->toBe('blog/{slug}');
    expect($collection->routeForSite('english'))->toBe('blog/{slug}');
});

test('a collection can be made structured with a max depth and expects a root page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Pages')
        ->set('handle', 'pages')
        ->set('structured', true)
        ->set('orderable', true)
        ->set('maxDepth', '3')
        ->set('expectsRoot', true)
        ->set('route', '{parent_uri}/{slug}')
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->structured())->toBeTrue();
    expect($collection->orderable())->toBeTrue();
    expect($collection->maxDepth())->toBe(3);
    expect($collection->expectsRoot())->toBeTrue();
});

test('max depth and expects root are dropped when a collection is not structured', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('structured', false)
        ->set('maxDepth', '3')
        ->set('expectsRoot', true)
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->maxDepth())->toBeNull();
    expect($collection->expectsRoot())->toBeFalse();
});

test('turning off structured in the form clears max depth and expects root', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->call('create')
        ->set('structured', true)
        ->set('maxDepth', '3')
        ->set('expectsRoot', true)
        ->set('structured', false)
        ->assertSet('maxDepth', '')
        ->assertSet('expectsRoot', false);
});

test('collection can be deleted', function () {
    $this->actingAs(User::factory()->create());

    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    Livewire::test(CollectionManager::class)->call('delete', $collection->id);

    expect(Collection::find($collection->id))->toBeNull();
});

test('collection cannot be deleted while it still has entries', function () {
    $this->actingAs(User::factory()->create());

    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'data' => ['title' => 'x']]);

    Livewire::test(CollectionManager::class)->call('delete', $collection->id);

    expect(Collection::find($collection->id))->not->toBeNull();
});

test('schema tab can be enabled and round-trips through edit', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('schemaEnabled', true)
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->schemaEnabled())->toBeTrue();

    Livewire::test(CollectionManager::class)
        ->call('edit', $collection->id)
        ->assertSet('schemaEnabled', true);
});

test('seo tab can be enabled and round-trips through edit', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('seoEnabled', true)
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->seoEnabled())->toBeTrue();

    Livewire::test(CollectionManager::class)
        ->call('edit', $collection->id)
        ->assertSet('seoEnabled', true);
});

test('the action after save can be configured and round-trips through edit', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('saveAction', 'create_another')
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->saveAction())->toBe('create_another');

    Livewire::test(CollectionManager::class)
        ->call('edit', $collection->id)
        ->assertSet('saveAction', 'create_another');
});

test('creating a collection requires the configure collections permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(CollectionManager::class)->call('create')->assertForbidden();
});

test('a user with configure collections permission can create a collection', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure collections']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(CollectionManager::class)
        ->call('create')
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->call('save')
        ->assertHasNoErrors();

    expect(Collection::sole()->handle)->toBe('blog');
});

test('editing a collection requires the configure collections permission', function () {
    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(CollectionManager::class)->call('edit', $collection->id)->assertForbidden();
});

test('a user with configure collections permission can edit a collection', function () {
    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure collections']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(CollectionManager::class)
        ->call('edit', $collection->id)
        ->assertSet('handle', 'blog');
});

test('saving a collection requires the configure collections permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->call('save')
        ->assertForbidden();
});

test('deleting a collection requires the configure collections permission', function () {
    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(CollectionManager::class)->call('delete', $collection->id)->assertForbidden();
});

test('a user with configure collections permission can delete a collection', function () {
    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure collections']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(CollectionManager::class)->call('delete', $collection->id);

    expect(Collection::find($collection->id))->toBeNull();
});

test('the action after save must be a known option', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('saveAction', 'not-a-real-option')
        ->call('save')
        ->assertHasErrors(['saveAction']);
});

test('collection handle can be regenerated from the title', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->call('create')
        ->set('title', 'Blog Posts')
        ->call('regenerateHandle')
        ->assertSet('handle', 'blog_posts');
});

test('visible order defaults to true for a new collection and can be turned off', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->assertSet('visibleOrder', true)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('visibleOrder', false)
        ->call('save')
        ->assertHasNoErrors();

    expect(Collection::sole()->visibleOrder())->toBeFalse();
});

test('an existing collection with visible order off loads that setting when edited', function () {
    $this->actingAs(User::factory()->create());
    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => ['visible_order' => false]]);

    Livewire::test(CollectionManager::class)
        ->call('edit', $collection->id)
        ->assertSet('visibleOrder', false);
});
