<?php

use App\Models\User;
use App\Modules\ActivityLog\Jobs\HardDeleteActivityLogYear;
use App\Modules\ActivityLog\Livewire\ActivityLogManager;
use App\Modules\ActivityLog\Models\ActivityLog;
use App\Modules\ActivityLog\Models\ActivityLogDeletionRequest;
use App\Modules\ActivityLog\Notifications\ConfirmActivityLogDeletion;
use App\Modules\Users\Models\Role;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('a superadmin requesting deletion creates a pending request and emails a confirmation link, without deleting anything', function () {
    Notification::fake();

    $admin = User::factory()->create();
    $this->actingAs($admin); // TestCase::actingAs grants Super Admin to a roleless user

    ActivityLog::create(['action' => 'created', 'description' => 'old entry', 'created_at' => '2023-05-01 00:00:00']);

    Livewire::test(ActivityLogManager::class)
        ->set('deleteYear', '2023')
        ->call('requestDeletion')
        ->assertHasNoErrors();

    $request = ActivityLogDeletionRequest::sole();
    expect($request->year)->toBe(2023);
    expect($request->status)->toBe('pending');
    expect($request->requested_by)->toBe($admin->id);

    Notification::assertSentTo($admin, ConfirmActivityLogDeletion::class);
    expect(ActivityLog::where('description', 'old entry')->exists())->toBeTrue(); // nothing deleted yet
});

test('a non-superadmin cannot request a deletion', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'view activity log']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(ActivityLogManager::class)
        ->set('deleteYear', '2023')
        ->call('requestDeletion')
        ->assertForbidden();

    expect(ActivityLogDeletionRequest::count())->toBe(0);
});

test('clicking the emailed link confirms the request but still does not delete anything', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $request = ActivityLogDeletionRequest::create([
        'year' => 2023, 'requested_by' => $admin->id, 'token_hash' => hash('sha256', 'plain-token'),
        'status' => 'pending', 'expires_at' => now()->addDay(),
    ]);

    ActivityLog::create(['action' => 'created', 'description' => 'old entry', 'created_at' => '2023-05-01 00:00:00']);

    $this->get(route('activity-log.confirm-delete', ['token' => 'plain-token']))->assertOk();

    expect($request->fresh()->status)->toBe('confirmed');
    expect(ActivityLog::where('description', 'old entry')->exists())->toBeTrue();
});

test('an expired confirmation link cannot confirm the request', function () {
    $admin = User::factory()->create();
    $this->actingAs($admin);

    $request = ActivityLogDeletionRequest::create([
        'year' => 2023, 'requested_by' => $admin->id, 'token_hash' => hash('sha256', 'plain-token'),
        'status' => 'pending', 'expires_at' => now()->subMinute(),
    ]);

    $this->get(route('activity-log.confirm-delete', ['token' => 'plain-token']))->assertOk();

    expect($request->fresh()->status)->toBe('pending');
});

test('the nightly command hard-deletes only the confirmed year and leaves other years alone', function () {
    $admin = User::factory()->create();

    ActivityLog::create(['action' => 'created', 'description' => 'from 2023', 'created_at' => '2023-06-01 00:00:00']);
    ActivityLog::create(['action' => 'created', 'description' => 'from 2024', 'created_at' => '2024-06-01 00:00:00']);

    $request = ActivityLogDeletionRequest::create([
        'year' => 2023, 'requested_by' => $admin->id, 'token_hash' => hash('sha256', 'plain-token'),
        'status' => 'confirmed', 'expires_at' => now()->addDay(),
    ]);

    Artisan::call('activity-log:process-deletions');

    expect(ActivityLog::where('description', 'from 2023')->exists())->toBeFalse();
    expect(ActivityLog::where('description', 'from 2024')->exists())->toBeTrue();

    $request->refresh();
    expect($request->status)->toBe('executed');
    expect($request->deleted_count)->toBe(1);
    expect($request->executed_at)->not->toBeNull();
});

test('the nightly command expires stale pending requests', function () {
    $admin = User::factory()->create();

    $request = ActivityLogDeletionRequest::create([
        'year' => 2023, 'requested_by' => $admin->id, 'token_hash' => hash('sha256', 'stale-token'),
        'status' => 'pending', 'expires_at' => now()->subHour(),
    ]);

    Artisan::call('activity-log:process-deletions');

    expect($request->fresh()->status)->toBe('expired');
});

test('the delete job is a no-op if the request is not confirmed', function () {
    $admin = User::factory()->create();

    ActivityLog::create(['action' => 'created', 'description' => 'from 2023', 'created_at' => '2023-06-01 00:00:00']);

    $request = ActivityLogDeletionRequest::create([
        'year' => 2023, 'requested_by' => $admin->id, 'token_hash' => hash('sha256', 'plain-token'),
        'status' => 'pending', 'expires_at' => now()->addDay(),
    ]);

    (new HardDeleteActivityLogYear($request->id))->handle();

    expect(ActivityLog::where('description', 'from 2023')->exists())->toBeTrue();
    expect($request->fresh()->status)->toBe('pending');
});
