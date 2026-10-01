<?php

use App\Models\User;
use App\Modules\Users\Livewire\UserManager;
use App\Modules\Users\Models\Role;
use App\Modules\Users\Services\PermissionRegistry;
use Livewire\Livewire;

test('a user starts unbanned, and ban()/unban() toggle it', function () {
    $user = User::factory()->create();

    expect($user->isBanned())->toBeFalse();

    $user->ban();
    expect($user->fresh()->isBanned())->toBeTrue();

    $user->unban();
    expect($user->fresh()->isBanned())->toBeFalse();
});

test('ban users is offered as a grantable permission', function () {
    expect(app(PermissionRegistry::class)->values())->toContain('ban users');
});

test('a banned user cannot log in', function () {
    $user = User::factory()->create();
    $user->ban();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('email');
    $this->assertGuest();
});

test('a user banned mid-session is logged out on their next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->ban();

    $this->get(route('dashboard'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('bulk-banning users requires the ban users permission', function () {
    $target = User::factory()->create();
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)->set('selected', [$target->id])->call('bulkBan')->assertForbidden();
});

test('a user with ban users permission can bulk-ban and bulk-unban users', function () {
    $target = User::factory()->create();
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users', 'ban users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)->set('selected', [$target->id])->call('bulkBan');
    expect($target->fresh()->isBanned())->toBeTrue();

    Livewire::test(UserManager::class)->set('selected', [$target->id])->call('bulkUnban');
    expect($target->fresh()->isBanned())->toBeFalse();
});

test('bulk-ban skips the acting user\'s own account', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users', 'ban users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)->set('selected', [$user->id])->call('bulkBan');

    expect($user->fresh()->isBanned())->toBeFalse();
});

test('a user with ban users permission can ban and unban another user one at a time', function () {
    $target = User::factory()->create();
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users', 'ban users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)->call('ban', $target->id);
    expect($target->fresh()->isBanned())->toBeTrue();

    Livewire::test(UserManager::class)->call('unban', $target->id);
    expect($target->fresh()->isBanned())->toBeFalse();
});

test('a user cannot ban their own account', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users', 'ban users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)->call('ban', $user->id);

    expect($user->fresh()->isBanned())->toBeFalse();
});

test('a user cannot unban their own account', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users', 'ban users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);
    $user->ban();

    Livewire::test(UserManager::class)->call('unban', $user->id);

    expect($user->fresh()->isBanned())->toBeTrue();
});

test('a user cannot delete their own account', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users', 'delete users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)->call('delete', $user->id);

    expect(User::find($user->id))->not->toBeNull();
});

test('bulk-unban skips the acting user\'s own account', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users', 'ban users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);
    $user->ban();

    Livewire::test(UserManager::class)->set('selected', [$user->id])->call('bulkUnban');

    expect($user->fresh()->isBanned())->toBeTrue();
});

test('single-row ban requires the ban users permission', function () {
    $target = User::factory()->create();
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)->call('ban', $target->id)->assertForbidden();
});

test('bulk-deleting users requires the delete users permission', function () {
    $target = User::factory()->create();
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)->set('selected', [$target->id])->call('bulkDelete')->assertForbidden();
});

test('a user with delete users permission can bulk-delete users, skipping their own account', function () {
    $target = User::factory()->create();
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view users', 'delete users']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(UserManager::class)
        ->set('selected', [$target->id, $user->id])
        ->call('bulkDelete');

    expect(User::find($target->id))->toBeNull();
    expect(User::find($user->id))->not->toBeNull();
});

test('toggling select all checks every listed user, then unchecks them', function () {
    $this->actingAs(User::factory()->create());
    $other = User::factory()->create();

    $component = Livewire::test(UserManager::class)->call('toggleSelectAll');

    expect($component->get('selected'))->toHaveCount(2);

    $component->call('toggleSelectAll');
    expect($component->get('selected'))->toBe([]);
});
