<?php

use App\Models\User;
use App\Modules\ActivityLog\Livewire\ActivityLogManager;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Redirects\Models\Redirect;
use App\Modules\Sites\Models\Site;
use App\Modules\Users\Models\Role;
use App\Modules\Users\Services\PermissionRegistry;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

test('creating a model with the LogsActivity trait writes a "created" entry', function () {
    $this->actingAs($admin = User::factory()->create());

    $site = Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    $entry = ActivityLog::where('subject_type', $site->getMorphClass())->where('subject_id', $site->id)->sole();

    expect($entry->action)->toBe('created');
    expect($entry->user_id)->toBe($admin->id);
    expect($entry->description)->toContain($admin->name)->toContain("site 'English'");
});

test('updating a model logs the changed fields, not the whole row', function () {
    $this->actingAs(User::factory()->create());

    $site = Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    $site->update(['name' => 'English (UK)']);

    $entry = ActivityLog::where('subject_type', $site->getMorphClass())->where('subject_id', $site->id)->where('action', 'updated')->sole();

    expect($entry->changes)->toBe(['name' => ['from' => 'English', 'to' => 'English (UK)']]);
});

test('a save that only touches an excluded column (e.g. Redirect hits) logs nothing', function () {
    $this->actingAs(User::factory()->create());

    $redirect = Redirect::create(['source' => '/old', 'target' => '/new', 'status_code' => 301, 'match_type' => 'exact', 'is_active' => true, 'hits' => 0]);

    ActivityLog::query()->delete();

    $redirect->increment('hits');

    expect(ActivityLog::where('subject_type', $redirect->getMorphClass())->where('subject_id', $redirect->id)->exists())->toBeFalse();
});

test('deleting a model logs a "deleted" entry', function () {
    $this->actingAs(User::factory()->create());

    $site = Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);
    $siteId = $site->id;

    $site->delete();

    $entry = ActivityLog::where('subject_type', $site->getMorphClass())->where('subject_id', $siteId)->where('action', 'deleted')->sole();

    expect($entry->description)->toContain("site 'English'");
});

test('a password change is never included in a logged diff', function () {
    $this->actingAs(User::factory()->create());

    $target = User::factory()->create();
    $target->forceFill(['password' => 'a-new-hash'])->save();

    $entry = ActivityLog::where('subject_type', $target->getMorphClass())->where('subject_id', $target->id)->where('action', 'updated')->first();

    expect($entry)->toBeNull(); // password is the only thing that changed, and it's hidden — nothing left to log
});

test('banning a user is logged as "banned", not a generic "updated"', function () {
    $this->actingAs(User::factory()->create());

    $target = User::factory()->create();
    $target->ban();

    $entry = ActivityLog::where('subject_type', $target->getMorphClass())->where('subject_id', $target->id)->where('action', 'banned')->sole();

    expect($entry->changes['banned_at']['from'])->toBeNull();
});

test('assigning roles to a user is logged even though pivot syncs skip model events', function () {
    $this->actingAs(User::factory()->create());

    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => []]);
    $target = User::factory()->create();

    $target->roles()->sync([$role->id]);

    // Simulate what UserForm::save() does explicitly (pivot syncs don't fire model events)
    ActivityLogger::log(
        action: 'roles_changed',
        subject: $target,
        description: "Someone changed roles for user '{$target->name}'",
        changes: ['roles' => ['from' => [], 'to' => ['editor']]],
    );

    expect(ActivityLog::where('action', 'roles_changed')->where('subject_id', $target->id)->exists())->toBeTrue();
});

test('logging in and out are both recorded', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password']);

    expect(ActivityLog::where('action', 'logged_in')->where('subject_id', $user->id)->exists())->toBeTrue();

    $this->post(route('logout'));

    expect(ActivityLog::where('action', 'logged_out')->where('subject_id', $user->id)->exists())->toBeTrue();
});

test('a failed login attempt is recorded without the password', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password']);

    $entry = ActivityLog::where('action', 'login_failed')->sole();

    expect($entry->properties)->not->toHaveKey('password');
    expect($entry->properties['email'])->toBe($user->email);
});

test('view activity log page requires the "view activity log" permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    $this->get(route('activity-log.activity-log.index'))->assertForbidden();
});

test('a user with "view activity log" permission can see it', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'view activity log']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    $this->get(route('activity-log.activity-log.index'))->assertOk();
});

test('view activity log is offered as a grantable permission', function () {
    expect(app(PermissionRegistry::class)->values())->toContain('view activity log');
});

test('exporting downloads only entries within the selected month', function () {
    $this->actingAs(User::factory()->create());

    ActivityLog::create(['action' => 'created', 'description' => 'in month', 'created_at' => '2026-03-15 10:00:00']);
    ActivityLog::create(['action' => 'created', 'description' => 'out of month', 'created_at' => '2026-04-01 10:00:00']);

    $response = Livewire::test(ActivityLogManager::class)
        ->set('exportPeriod', 'month')
        ->set('exportMonth', '2026-03')
        ->call('export');

    $response->assertFileDownloaded('activity-log-2026-03.json');
});

test('restoring an exported file upserts entries back, keyed by their original id', function () {
    $admin = User::factory()->create();
    $role = Role::create(['handle' => 'manager', 'title' => 'Manager', 'permissions' => ['access cp', 'view activity log', 'import activity log']]);
    $admin->roles()->attach($role);
    $this->actingAs($admin);

    $original = ActivityLog::create([
        'action' => 'created',
        'description' => 'entry to restore',
        'changes' => ['name' => ['from' => 'a', 'to' => 'b']],
        'created_at' => '2026-01-01 09:00:00',
    ]);

    $payload = [$original->fresh()->toArray()];
    $file = UploadedFile::fake()->createWithContent('export.json', json_encode($payload));

    $original->delete();
    expect(ActivityLog::find($original->id))->toBeNull();

    Livewire::test(ActivityLogManager::class)
        ->set('restoreFile', $file)
        ->call('restore');

    $restored = ActivityLog::find($original->id);
    expect($restored)->not->toBeNull();
    expect($restored->description)->toBe('entry to restore');
    expect($restored->changes)->toBe(['name' => ['from' => 'a', 'to' => 'b']]);
});

test('restoring requires the "import activity log" permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'viewer', 'title' => 'Viewer', 'permissions' => ['access cp', 'view activity log']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    $file = UploadedFile::fake()->createWithContent('export.json', json_encode([]));

    Livewire::test(ActivityLogManager::class)
        ->set('restoreFile', $file)
        ->call('restore')
        ->assertForbidden();
});

test('"view activity log detail", "export activity log" and "import activity log" are offered as grantable permissions', function () {
    $values = app(PermissionRegistry::class)->values();

    expect($values)->toContain('view activity log detail');
    expect($values)->toContain('export activity log');
    expect($values)->toContain('import activity log');
});

test('when disabled via config, nothing is logged and the page 404s', function () {
    config(['activity-log.enabled' => false]);

    $this->actingAs($admin = User::factory()->create());

    // creating the admin itself would normally log a "created" entry
    expect(ActivityLog::count())->toBe(0);

    $site = Site::create(['handle' => 'english', 'name' => 'English', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);
    expect(ActivityLog::where('subject_id', $site->id)->exists())->toBeFalse();

    $this->get(route('activity-log.activity-log.index'))->assertNotFound();
});
