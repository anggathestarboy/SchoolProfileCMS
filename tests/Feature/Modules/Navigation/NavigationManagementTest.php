<?php

use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Navigation\Livewire\NavigationManager;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Users\Models\Role;
use Livewire\Livewire;

test('navigations page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin-panel/navigations')->assertOk();
});

test('navigation can be created', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(NavigationManager::class)
        ->set('title', 'Main menu')
        ->set('handle', 'main')
        ->call('save')
        ->assertHasNoErrors();

    $navigation = Navigation::sole();

    expect($navigation->title)->toBe('Main menu');
    expect($navigation->handle)->toBe('main');
});

test('handle can be generated from the title via the regenerate button', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(NavigationManager::class)
        ->call('create')
        ->set('title', 'Main Menu!')
        ->assertSet('handle', '')
        ->call('regenerateHandle')
        ->assertSet('handle', 'main-menu')
        ->set('title', 'Yet Another Title')
        ->assertSet('handle', 'main-menu')
        ->call('regenerateHandle')
        ->assertSet('handle', 'yet-another-title');
});

test('editing an existing navigation does not overwrite its handle when the title changes', function () {
    $this->actingAs(User::factory()->create());

    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main']);

    Livewire::test(NavigationManager::class)
        ->call('edit', $navigation->id)
        ->set('title', 'Primary menu')
        ->assertSet('handle', 'main');
});

test('navigation handle must be unique', function () {
    $this->actingAs(User::factory()->create());

    Navigation::create(['title' => 'Main menu', 'handle' => 'main']);

    Livewire::test(NavigationManager::class)
        ->set('title', 'Another menu')
        ->set('handle', 'main')
        ->call('save')
        ->assertHasErrors(['handle']);
});

test('navigation can be updated', function () {
    $this->actingAs(User::factory()->create());

    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main']);

    Livewire::test(NavigationManager::class)
        ->call('edit', $navigation->id)
        ->set('title', 'Primary menu')
        ->call('save')
        ->assertHasNoErrors();

    expect($navigation->fresh()->title)->toBe('Primary menu');
});

test('navigation can be deleted', function () {
    $this->actingAs(User::factory()->create());

    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main']);

    Livewire::test(NavigationManager::class)->call('delete', $navigation->id);

    expect(Navigation::find($navigation->id))->toBeNull();
});

test('protected navigations cannot be deleted', function () {
    $this->actingAs(User::factory()->create());

    config(['cms.protected_navigations' => ['sidebar-admin']]);
    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);

    Livewire::test(NavigationManager::class)->call('delete', $navigation->id);

    expect(Navigation::find($navigation->id))->not->toBeNull();
});

test('protected navigations cannot be edited', function () {
    $this->actingAs(User::factory()->create());

    config(['cms.protected_navigations' => ['sidebar-admin']]);
    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);

    Livewire::test(NavigationManager::class)
        ->call('edit', $navigation->id)
        ->assertSet('editingId', null);
});

test('creating a navigation with the blueprint checkbox on also creates its blueprint', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(NavigationManager::class)
        ->set('title', 'Main menu')
        ->set('handle', 'main')
        ->set('createBlueprint', true)
        ->call('save')
        ->assertHasNoErrors();

    $navigation = Navigation::sole();

    expect($navigation->effectiveBlueprint())->not->toBeNull();
    expect($navigation->effectiveBlueprint()->namespace)->toBe('navigations.main');
});

test('creating a navigation without the blueprint checkbox uses the picked blueprint', function () {
    $this->actingAs(User::factory()->create());

    $blueprint = Blueprint::create([
        'namespace' => null,
        'handle' => 'custom_nav',
        'data' => ['tabs' => [], 'fields' => [['handle' => 'icon', 'display' => 'Icon', 'type' => 'text']]],
    ]);

    Livewire::test(NavigationManager::class)
        ->set('title', 'Main menu')
        ->set('handle', 'main')
        ->set('createBlueprint', false)
        ->set('blueprint', $blueprint->handle)
        ->call('save')
        ->assertHasNoErrors();

    $navigation = Navigation::sole();

    expect($navigation->effectiveBlueprint()->handle)->toBe('custom_nav');
});

test('editing a navigation preselects its currently picked blueprint', function () {
    $this->actingAs(User::factory()->create());

    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main', 'settings' => ['blueprint' => 'custom_nav']]);
    Blueprint::create(['namespace' => null, 'handle' => 'custom_nav', 'data' => ['fields' => []]]);

    Livewire::test(NavigationManager::class)
        ->call('edit', $navigation->id)
        ->assertSet('blueprint', 'custom_nav')
        ->assertSet('createBlueprint', false);
});

test('edit tree is disabled while the navigation\'s blueprint has no fields', function () {
    $this->actingAs(User::factory()->create());

    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main']);
    Blueprint::create(['namespace' => 'navigations.main', 'handle' => 'main', 'data' => ['fields' => []]]);

    $this->get('/admin-panel/navigations')->assertSee('Add fields to this navigation');
});

test('edit tree stays enabled for a protected navigation even with no blueprint', function () {
    $this->actingAs(User::factory()->create());

    config(['cms.protected_navigations' => ['sidebar-admin']]);
    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);

    Livewire::test(NavigationManager::class)
        ->assertSeeHtml(route('navigation.edit', $navigation->handle));
});

test('creating a navigation requires the configure navigation permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationManager::class)->call('create')->assertStatus(403);
});

test('a user with configure navigation permission can create a navigation', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure navigation']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationManager::class)
        ->call('create')
        ->set('title', 'Main menu')
        ->set('handle', 'main')
        ->call('save')
        ->assertHasNoErrors();

    expect(Navigation::where('handle', 'main')->exists())->toBeTrue();
});

test('editing a navigation requires the configure navigation permission', function () {
    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main']);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationManager::class)->call('edit', $navigation->id)->assertStatus(403);
});

test('a user with configure navigation permission can edit a navigation', function () {
    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main']);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure navigation']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationManager::class)
        ->call('edit', $navigation->id)
        ->assertSet('handle', 'main');
});

test('saving a navigation requires the configure navigation permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationManager::class)
        ->set('title', 'Main menu')
        ->set('handle', 'main')
        ->call('save')
        ->assertStatus(403);
});

test('deleting a navigation requires the configure navigation permission', function () {
    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main']);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationManager::class)->call('delete', $navigation->id)->assertStatus(403);
});

test('a user with configure navigation permission can delete a navigation', function () {
    $navigation = Navigation::create(['title' => 'Main menu', 'handle' => 'main']);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure navigation']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationManager::class)->call('delete', $navigation->id);

    expect(Navigation::find($navigation->id))->toBeNull();
});
