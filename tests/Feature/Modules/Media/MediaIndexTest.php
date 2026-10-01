<?php

use App\Models\User;
use App\Modules\Media\Livewire\Index;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('deleting a single file requires opening the confirm modal before it is actually removed', function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('public');

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);

    $component = Livewire::test(Index::class, ['container' => 'photos'])
        ->call('askDeleteFile', $asset->id)
        ->assertSet('showDeleteFile', true)
        ->assertSet('deleteFileId', $asset->id);

    // Opening the modal alone must not delete anything yet.
    expect(AssetMeta::find($asset->id))->not->toBeNull();

    $component->call('confirmDeleteFile')->assertSet('showDeleteFile', false);

    expect(AssetMeta::find($asset->id))->toBeNull();
});

test('a video asset renders as a muted, non-interactive video thumbnail in the grid', function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('public');

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'clip.mp4', 'filename' => 'clip', 'extension' => 'mp4', 'path' => 'clip.mp4', 'meta' => ['mime_type' => 'video/mp4']]);

    $html = Livewire::test(Index::class, ['container' => 'photos'])->html();

    expect($html)->toContain('<video');
    expect($html)->not->toContain('controls');
});

test('a video asset gets a playable, controlled video element in the preview modal', function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('public');

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'clip.mp4', 'filename' => 'clip', 'extension' => 'mp4', 'path' => 'clip.mp4', 'meta' => ['mime_type' => 'video/mp4']]);

    $html = Livewire::test(Index::class, ['container' => 'photos'])
        ->call('openPreview', $asset->id)
        ->html();

    expect($html)->toContain('<video');
    expect($html)->toContain('controls');
    expect($html)->toContain($asset->url);
});

test('a video asset on the public disk is served through the range-capable stream route, not the raw storage symlink', function () {
    $this->actingAs(User::factory()->create());
    Storage::fake('public');

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'clip.mp4', 'filename' => 'clip', 'extension' => 'mp4', 'path' => 'clip.mp4', 'meta' => ['mime_type' => 'video/mp4']]);

    // The plain storage symlink is served directly by the web server and ignores Range
    // requests, which browsers need to seek to a trailing moov atom before they can even
    // start playback — so video must go through the app's own Range-aware route instead.
    expect($asset->url)->toBe(route('media.stream.public', ['path' => 'photos/clip.mp4']));
});
