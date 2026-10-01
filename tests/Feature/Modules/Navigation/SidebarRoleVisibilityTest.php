<?php

use App\Models\User;
use App\Modules\Navigation\Livewire\NavigationTreeEditor;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Navigation\Services\NavigationTreeService;
use App\Modules\Users\Models\Role;
use Livewire\Livewire;

function makeRoleRestrictedUser(string $roleHandle): User
{
    $user = User::factory()->create();
    $role = Role::firstOrCreate(['handle' => $roleHandle], ['title' => ucfirst($roleHandle), 'permissions' => ['access cp']]);
    $user->roles()->attach($role);

    return $user;
}

test('a sidebar item is hidden from a user whose role is not in its Visible to list', function () {
    $user = makeRoleRestrictedUser('editor');
    $this->actingAs($user);

    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);
    app(NavigationTreeService::class)->addItem($navigation, null, [
        'type' => 'internal', 'title' => 'Users', 'url' => '/admin-panel/users', 'roles' => ['reviewer'],
    ]);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('/admin-panel/users');
});

test('a sidebar item is shown to a user whose role is in its Visible to list', function () {
    $user = makeRoleRestrictedUser('editor');
    $this->actingAs($user);

    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);
    app(NavigationTreeService::class)->addItem($navigation, null, [
        'type' => 'internal', 'title' => 'Users', 'url' => '/admin-panel/users', 'roles' => ['editor', 'reviewer'],
    ]);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('/admin-panel/users');
});

test('a sidebar item with no roles checked is hidden from everyone but a super admin', function () {
    $user = makeRoleRestrictedUser('editor');
    $this->actingAs($user);

    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);
    app(NavigationTreeService::class)->addItem($navigation, null, [
        'type' => 'internal', 'title' => 'Users', 'url' => '/admin-panel/users', 'roles' => [],
    ]);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('/admin-panel/users');
});

test('a super admin sees every sidebar item regardless of its Visible to list', function () {
    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);
    app(NavigationTreeService::class)->addItem($navigation, null, [
        'type' => 'internal', 'title' => 'Users', 'url' => '/admin-panel/users', 'roles' => [],
    ]);

    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertOk()->assertSee('/admin-panel/users', false);
});

test('a sidebar group is hidden once none of its children are visible to the user\'s role', function () {
    $user = makeRoleRestrictedUser('editor');
    $this->actingAs($user);

    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);
    $trees = app(NavigationTreeService::class);
    $tree = $trees->withAddedItem([], null, ['type' => 'internal', 'title' => 'Settings Group', 'url' => null, 'roles' => ['editor']]);
    $groupId = $tree[0]['id'];
    $tree = $trees->withAddedItem($tree, $groupId, ['type' => 'internal', 'title' => 'Users', 'url' => '/admin-panel/users', 'roles' => ['reviewer']]);
    $trees->save($navigation, $tree);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->not->toContain('Settings Group');
});

test('a sidebar group stays visible if at least one of its children is visible to the user\'s role', function () {
    $user = makeRoleRestrictedUser('editor');
    $this->actingAs($user);

    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);
    $trees = app(NavigationTreeService::class);
    $tree = $trees->withAddedItem([], null, ['type' => 'internal', 'title' => 'Settings Group', 'url' => null, 'roles' => ['editor']]);
    $groupId = $tree[0]['id'];
    $tree = $trees->withAddedItem($tree, $groupId, ['type' => 'internal', 'title' => 'Users', 'url' => '/admin-panel/users', 'roles' => ['editor']]);
    $tree = $trees->withAddedItem($tree, $groupId, ['type' => 'internal', 'title' => 'Roles', 'url' => '/admin-panel/roles', 'roles' => ['reviewer']]);
    $trees->save($navigation, $tree);

    $html = $this->get(route('dashboard'))->assertOk()->getContent();

    expect($html)->toContain('Settings Group');
    expect($html)->toContain('/admin-panel/users');
    expect($html)->not->toContain('/admin-panel/roles');
});

test('the sidebar-admin tree editor offers a Visible to role checklist per item', function () {
    $this->actingAs(User::factory()->create());
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => []]);
    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('addRoot')
        ->assertSee('Visible to')
        ->assertSee('Editor')
        ->assertSet('tree.0.roles', [])
        ->set('tree.0.title', 'Users')
        ->set('tree.0.url', '/admin-panel/users')
        ->set('tree.0.roles', [$role->handle])
        ->call('save')
        ->assertHasNoErrors();

    $tree = app(NavigationTreeService::class)->load($navigation);
    expect($tree[0]['roles'])->toBe(['editor']);
});

test('a super admin role is not offered in the Visible to checklist', function () {
    $this->actingAs(User::factory()->create());
    // The "grandfather roleless users" migration already creates this role
    // as part of every fresh migrate.
    Role::firstOrCreate(['handle' => 'super_admin'], ['title' => 'Super Admin', 'permissions' => ['super']]);
    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);

    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('addRoot')
        ->assertSee('Visible to');

    expect($component->instance()->availableRoles->pluck('handle'))->not->toContain('super_admin');
});

test('a collapsed item shows its granted roles as small badges', function () {
    $this->actingAs(User::factory()->create());
    Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => []]);
    Role::create(['handle' => 'reviewer', 'title' => 'Reviewer', 'permissions' => []]);

    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);
    app(NavigationTreeService::class)->addItem($navigation, null, [
        'type' => 'internal', 'title' => 'Users', 'url' => '/admin-panel/users', 'roles' => ['editor', 'reviewer'],
    ]);

    // Collapsed by default (nothing in $expanded), so the badges should show.
    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertSee('Editor')
        ->assertSee('Reviewer');
});

test('ctrl+s and cmd+s are both wired to save the navigation tree', function () {
    $this->actingAs(User::factory()->create());
    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);

    $html = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])->html();

    expect($html)->toContain('keydown.window.ctrl.s.prevent="$wire.save()"');
    expect($html)->toContain('keydown.window.meta.s.prevent="$wire.save()"');
});
