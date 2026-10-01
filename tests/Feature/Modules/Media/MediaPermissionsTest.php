<?php

use App\Models\User;
use App\Modules\Media\Livewire\Index;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Users\Models\Role;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * Note: Livewire::test() dispatches through a synthetic HTTP request whose
 * RequestBroker deliberately keeps HttpException/AuthorizationException
 * *handled* (see vendor/livewire/livewire's RequestBroker::
 * temporarilyDisableExceptionHandlingAndMiddleware), so a 403 from
 * authorizePermission() never reaches the test as a thrown exception - it
 * surfaces as a real 403 response instead. Assert with ->assertForbidden(),
 * not ->throws(HttpException::class).
 */
function restrictedMediaUser(array $extraPermissions = []): User
{
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted-'.uniqid(), 'title' => 'Restricted', 'permissions' => ['access cp', ...$extraPermissions]]);
    $user->roles()->attach($role);

    return $user;
}

test('mounting the index requires view permission on the resolved container', function () {
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $this->actingAs(restrictedMediaUser());

    Livewire::test(Index::class, ['container' => 'photos'])->assertForbidden();
});

test('a user with view permission can mount the index', function () {
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->assertOk()
        ->assertSet('container', 'photos');
});

test('switching containers re-checks view permission', function () {
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    AssetContainer::create(['handle' => 'docs', 'title' => 'Docs', 'disk' => 'public']);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('container', 'docs')
        ->assertForbidden();
});

test('uploading requires the upload permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('uploads', [UploadedFile::fake()->image('new.jpg')])
        ->assertForbidden();
});

test('a user with upload permission can upload a file', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'upload photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('uploads', [UploadedFile::fake()->image('new.jpg')])
        ->assertHasNoErrors();

    Storage::disk('public')->assertExists('photos/new.jpg');
});

test('creating a folder requires the edit folders permission', function () {
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('newFolderName', 'gallery')
        ->call('createFolder')
        ->assertForbidden();
});

test('a user with edit folders permission can create a folder', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'edit folders photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('newFolderName', 'gallery')
        ->call('createFolder')
        ->assertSet('showCreateFolder', false);

    Storage::disk('public')->assertExists('photos/gallery');
});

test('renaming a folder requires the edit folders permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('renameFolderPath', 'gallery')
        ->set('renameFolderValue', 'renamed')
        ->call('renameFolder')
        ->assertForbidden();
});

test('deleting a folder requires the edit folders permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('deleteFolderPath', 'gallery')
        ->call('confirmDeleteFolder')
        ->assertForbidden();
});

test('a user with edit folders permission can delete a folder', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    $this->actingAs(restrictedMediaUser(['view photos assets', 'edit folders photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('deleteFolderPath', 'gallery')
        ->call('confirmDeleteFolder')
        ->assertSet('showDeleteFolder', false);

    Storage::disk('public')->assertMissing('photos/gallery');
});

test('renaming a file requires the rename permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->put('photos/sunset.webp', 'x');
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'edit photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('renameFileId', $asset->id)
        ->set('renameFileValue', 'sunrise')
        ->set('renameFileExtension', 'webp')
        ->call('renameFile')
        ->assertForbidden();
});

test('a user with rename permission can rename a file', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->put('photos/sunset.webp', 'x');
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'rename photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->set('renameFileId', $asset->id)
        ->set('renameFileValue', 'sunrise')
        ->set('renameFileExtension', 'webp')
        ->call('renameFile')
        ->assertSet('showRenameFile', false);

    expect(AssetMeta::find($asset->id)->basename)->toBe('sunrise.webp');
});

test('deleting a file requires the delete permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('askDeleteFile', $asset->id)
        ->call('confirmDeleteFile')
        ->assertForbidden();
});

test('a user with delete permission can delete a file', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'delete photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('askDeleteFile', $asset->id)
        ->call('confirmDeleteFile')
        ->assertSet('showDeleteFile', false);

    expect(AssetMeta::find($asset->id))->toBeNull();
});

test('saving asset metadata requires the edit permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('openPreview', $asset->id)
        ->set('previewTitle', 'Sunset')
        ->set('previewAlt', 'A sunset')
        ->call('savePreviewMeta')
        ->assertForbidden();
});

test('a user with edit permission can save asset metadata', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'edit photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('openPreview', $asset->id)
        ->set('previewTitle', 'Sunset')
        ->set('previewAlt', 'A sunset')
        ->call('savePreviewMeta')
        ->assertSet('showPreview', false);

    expect(AssetMeta::find($asset->id)->meta['data']['alt'])->toBe('A sunset');
});

test('moving a file via drag-drop requires the move permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('dropOnFolder', 'file', (string) $asset->id, 'gallery')
        ->assertForbidden();
});

test('a user with move permission can drag-drop a file into a folder', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    Storage::disk('public')->put('photos/sunset.webp', 'x');
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'move photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('dropOnFolder', 'file', (string) $asset->id, 'gallery');

    expect(AssetMeta::find($asset->id)->folder)->toBe('gallery');
});

test('pasting a cut file requires the move permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    Storage::disk('public')->put('photos/sunset.webp', 'x');
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('cutFile', $asset->id)
        ->call('goToPath', 'gallery')
        ->call('paste')
        ->assertForbidden();
});

test('a user with move permission can paste a cut file', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    Storage::disk('public')->put('photos/sunset.webp', 'x');
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'move photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('cutFile', $asset->id)
        ->call('goToPath', 'gallery')
        ->call('paste');

    expect(AssetMeta::find($asset->id)->folder)->toBe('gallery');
});

test('duplicating a file requires the edit permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->put('photos/sunset.webp', 'x');
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('duplicateFile', $asset->id)
        ->assertForbidden();
});

test('a user with edit permission can duplicate a file', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->put('photos/sunset.webp', 'x');
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);
    $this->actingAs(restrictedMediaUser(['view photos assets', 'edit photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('duplicateFile', $asset->id);

    expect(AssetMeta::where('container', 'photos')->count())->toBe(2);
});

test('duplicating a folder requires the edit folders permission', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('duplicateFolder', 'gallery')
        ->assertForbidden();
});

test('a user with edit folders permission can duplicate a folder', function () {
    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/gallery');
    $this->actingAs(restrictedMediaUser(['view photos assets', 'edit folders photos assets']));

    Livewire::test(Index::class, ['container' => 'photos'])
        ->call('duplicateFolder', 'gallery');

    Storage::disk('public')->assertExists('photos/gallery (1)');
});

test('mounting the index with no container defaults to the first viewable container', function () {
    AssetContainer::create(['handle' => 'docs', 'title' => 'Docs', 'disk' => 'public']);
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $this->actingAs(restrictedMediaUser(['view photos assets']));

    Livewire::test(Index::class)->assertSet('container', 'photos');
});
