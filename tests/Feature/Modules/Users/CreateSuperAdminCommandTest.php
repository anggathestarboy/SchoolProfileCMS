<?php

use App\Models\User;
use App\Modules\Users\Models\Role;

test('users:create-super-admin creates a user with the super_admin role', function () {
    $this->artisan('users:create-super-admin', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'password123',
    ])->assertSuccessful();

    $user = User::where('email', 'jane@example.com')->sole();

    expect($user->name)->toBe('Jane Admin');
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->isSuper())->toBeTrue();

    $role = Role::where('handle', 'super_admin')->sole();
    expect($role->permissions)->toBe(['super']);
    expect($user->roles->pluck('handle')->all())->toBe(['super_admin']);
});

test('users:create-super-admin reuses an existing super_admin role instead of duplicating it', function () {
    // The "grandfather roleless users" migration already creates this role
    // as part of every fresh migrate — confirm the baseline before asserting
    // the command doesn't add a duplicate on top of it.
    expect(Role::where('handle', 'super_admin')->count())->toBe(1);

    $this->artisan('users:create-super-admin', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'password123',
    ])->assertSuccessful();

    expect(Role::where('handle', 'super_admin')->count())->toBe(1);
});

test('users:create-super-admin refuses to run when users already exist, unless --force is passed', function () {
    User::factory()->create();

    $this->artisan('users:create-super-admin', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'password123',
    ])->assertFailed();

    expect(User::count())->toBe(1);

    $this->artisan('users:create-super-admin', [
        '--name' => 'Jane Admin',
        '--email' => 'jane@example.com',
        '--password' => 'password123',
        '--force' => true,
    ])->assertSuccessful();

    expect(User::count())->toBe(2);
});

test('users:create-super-admin re-prompts for an invalid or already-used email', function () {
    User::factory()->create(['email' => 'taken@example.com']);

    $this->artisan('users:create-super-admin', ['--force' => true])
        ->expectsQuestion('Admin name', 'Jane Admin')
        ->expectsQuestion('Admin email', 'not-an-email')
        ->expectsOutput('Enter a valid email address.')
        ->expectsQuestion('Admin email', 'taken@example.com')
        ->expectsOutput('That email is already in use.')
        ->expectsQuestion('Admin email', 'jane@example.com')
        ->expectsQuestion('Admin password (min 8 characters)', 'password123')
        ->assertSuccessful();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});

test('users:create-super-admin re-prompts for a too-short password', function () {
    $this->artisan('users:create-super-admin')
        ->expectsQuestion('Admin name', 'Jane Admin')
        ->expectsQuestion('Admin email', 'jane@example.com')
        ->expectsQuestion('Admin password (min 8 characters)', 'short')
        ->expectsOutput('Password must be at least 8 characters.')
        ->expectsQuestion('Admin password (min 8 characters)', 'longenough123')
        ->assertSuccessful();

    expect(User::where('email', 'jane@example.com')->exists())->toBeTrue();
});
