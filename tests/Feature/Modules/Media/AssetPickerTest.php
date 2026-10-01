<?php

use App\Models\User;
use App\Modules\Media\Livewire\AssetPicker;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('opening the picker loads the container listing and shows the modal', function () {
    $this->actingAs(User::factory()->create());

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);

    Livewire::test(AssetPicker::class)
        ->call('open', ['gallery'], 'photos', [])
        ->assertSet('container', 'photos')
        ->assertSet('targetPath', ['gallery'])
        ->assertSee('sunset.webp');
});

test('toggling and confirming dispatches the selected ids back to the given path', function () {
    $this->actingAs(User::factory()->create());

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.webp', 'filename' => 'sunset', 'extension' => 'webp', 'path' => 'sunset.webp', 'meta' => []]);

    Livewire::test(AssetPicker::class)
        ->call('open', ['sections', 0, 'gallery'], 'photos', [])
        ->call('toggle', $asset->id)
        ->assertSet('selectedIds', [$asset->id])
        ->call('confirm')
        ->assertDispatched('assets-selected', path: ['sections', 0, 'gallery'], ids: [$asset->id]);
});

test('toggle refuses to select past the max passed to open()', function () {
    $this->actingAs(User::factory()->create());

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $first = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'one.webp', 'filename' => 'one', 'extension' => 'webp', 'path' => 'one.webp', 'meta' => []]);
    $second = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'two.webp', 'filename' => 'two', 'extension' => 'webp', 'path' => 'two.webp', 'meta' => []]);
    $third = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'three.webp', 'filename' => 'three', 'extension' => 'webp', 'path' => 'three.webp', 'meta' => []]);

    Livewire::test(AssetPicker::class)
        ->call('open', ['gallery'], 'photos', [], 2)
        ->call('toggle', $first->id)
        ->call('toggle', $second->id)
        ->assertSet('selectedIds', [$first->id, $second->id])
        ->call('toggle', $third->id)
        ->assertSet('selectedIds', [$first->id, $second->id]);
});

test('toggle still allows deselecting when already at the max', function () {
    $this->actingAs(User::factory()->create());

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    $first = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'one.webp', 'filename' => 'one', 'extension' => 'webp', 'path' => 'one.webp', 'meta' => []]);
    $second = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'two.webp', 'filename' => 'two', 'extension' => 'webp', 'path' => 'two.webp', 'meta' => []]);

    Livewire::test(AssetPicker::class)
        ->call('open', ['gallery'], 'photos', [$first->id, $second->id], 2)
        ->call('toggle', $first->id)
        ->assertSet('selectedIds', [$second->id]);
});

test('already-selected assets show in their own strip even when browsing a different folder', function () {
    $this->actingAs(User::factory()->create());

    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/vacation');
    $preSelected = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'existing.webp', 'filename' => 'existing', 'extension' => 'webp', 'path' => 'existing.webp', 'meta' => []]);
    AssetMeta::create(['container' => 'photos', 'folder' => 'vacation', 'basename' => 'beach.webp', 'filename' => 'beach', 'extension' => 'webp', 'path' => 'vacation/beach.webp', 'meta' => []]);

    Livewire::test(AssetPicker::class)
        ->call('open', ['gallery'], 'photos', [$preSelected->id], 2)
        ->call('goToFolder', 'vacation')
        ->assertSee('existing.webp')
        ->assertSee('beach.webp');
});

test('picker can browse into a sub-folder', function () {
    $this->actingAs(User::factory()->create());

    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);
    Storage::disk('public')->makeDirectory('photos/vacation');
    AssetMeta::create(['container' => 'photos', 'folder' => 'vacation', 'basename' => 'beach.webp', 'filename' => 'beach', 'extension' => 'webp', 'path' => 'vacation/beach.webp', 'meta' => []]);

    Livewire::test(AssetPicker::class)
        ->call('open', ['gallery'], 'photos', [])
        ->assertDontSee('beach.webp')
        ->call('goToFolder', 'vacation')
        ->assertSee('beach.webp');
});

test('toggle refuses to select an asset that fails the field\'s validation rule', function () {
    $this->actingAs(User::factory()->create());

    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);

    Storage::disk('public')->put('photos/report.pdf', 'not actually an image');
    $document = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'report.pdf', 'filename' => 'report', 'extension' => 'pdf', 'path' => 'report.pdf', 'meta' => []]);

    Storage::disk('public')->putFileAs('photos', UploadedFile::fake()->image('sunset.jpg'), 'sunset.jpg');
    $image = AssetMeta::create(['container' => 'photos', 'folder' => '', 'basename' => 'sunset.jpg', 'filename' => 'sunset', 'extension' => 'jpg', 'path' => 'sunset.jpg', 'meta' => []]);

    Livewire::test(AssetPicker::class)
        ->call('open', ['gallery'], 'photos', [], null, 'image')
        ->call('toggle', $document->id)
        ->assertSet('selectedIds', [])
        ->call('toggle', $image->id)
        ->assertSet('selectedIds', [$image->id]);
});

test('uploading a file adds it to the container and auto-selects it', function () {
    $this->actingAs(User::factory()->create());

    Storage::fake('public');
    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);

    $component = Livewire::test(AssetPicker::class)
        ->call('open', ['gallery'], 'photos', [])
        ->set('uploads', [UploadedFile::fake()->image('new-photo.jpg')])
        ->assertHasNoErrors();

    $asset = AssetMeta::where('container', 'photos')->where('basename', 'new-photo.jpg')->first();

    expect($asset)->not->toBeNull();
    Storage::disk('public')->assertExists('photos/new-photo.jpg');
    $component->assertSet('selectedIds', [$asset->id]);
});
