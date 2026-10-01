<?php

use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Livewire\CollectionManager;
use App\Modules\Collections\Livewire\EntryForm;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

function makeLivePreviewCollection(bool $livePreview = false): Collection
{
    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => [
        ['handle' => 'status', 'display' => 'Status', 'type' => 'select', 'required' => true, 'placeholder' => null, 'default' => null, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => null, 'options' => ['draft', 'published']],
    ]]]);

    return Collection::create(['handle' => 'blog', 'title' => 'Blog', 'settings' => ['live_preview' => $livePreview]]);
}

test('the live preview toggle can be turned on for a collection', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(CollectionManager::class)
        ->set('title', 'Blog')
        ->set('handle', 'blog')
        ->set('livePreview', true)
        ->call('save')
        ->assertHasNoErrors();

    $collection = Collection::sole();

    expect($collection->livePreview())->toBeTrue();
});

test('the entry form has no preview url when live preview is off', function () {
    $this->actingAs(User::factory()->create());
    $collection = makeLivePreviewCollection(livePreview: false);

    $previewUrl = Livewire::test(EntryForm::class, ['collection' => $collection, 'site' => 'indonesia'])
        ->instance()->previewUrl;

    expect($previewUrl)->toBeNull();
});

test('the entry form caches an in-progress preview payload when live preview is on', function () {
    $this->actingAs(User::factory()->create());
    $collection = makeLivePreviewCollection(livePreview: true);

    $component = Livewire::test(EntryForm::class, ['collection' => $collection, 'site' => 'indonesia'])
        ->set('title', 'Draft headline');

    $token = $component->instance()->previewToken;

    expect($token)->not->toBe('');
    expect($component->instance()->previewUrl)->toContain($token);

    $payload = Cache::get("entry-preview:{$token}");

    expect($payload['collection'])->toBe('blog');
    expect($payload['attributes']['data']['title'])->toBe('Draft headline');
});

test('the preview route renders a draft entry using its cached in-progress data', function () {
    $this->actingAs(User::factory()->create());
    $collection = makeLivePreviewCollection(livePreview: true);

    Blueprint::where('handle', 'post')->update(['data' => ['fields' => [], 'template' => 'tests-preview-template']]);
    View::addLocation(base_path('tests/Feature/Modules/Collections/fixtures'));

    $entry = Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'blueprint' => 'post', 'status' => 'draft', 'slug' => 'preview-me', 'data' => ['title' => 'Original', 'status' => 'draft']]);

    $component = Livewire::test(EntryForm::class, ['collection' => $collection, 'entry' => $entry])
        ->set('title', 'Changed via live preview');

    $token = $component->instance()->previewToken;

    $this->get(route('collections.preview', ['token' => $token]))
        ->assertOk()
        ->assertSee('Changed via live preview');
});

test('the preview route 404s once the collection turns live preview off', function () {
    $this->actingAs(User::factory()->create());
    $collection = makeLivePreviewCollection(livePreview: true);

    $component = Livewire::test(EntryForm::class, ['collection' => $collection, 'site' => 'indonesia']);
    $token = $component->instance()->previewToken;

    $collection->update(['settings' => ['live_preview' => false]]);

    $this->get(route('collections.preview', ['token' => $token]))->assertNotFound();
});

test('the preview route 404s for an unknown token', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('collections.preview', ['token' => 'does-not-exist']))->assertNotFound();
});

test('the live preview opens in a new browser tab', function () {
    $this->actingAs(User::factory()->create());
    $collection = makeLivePreviewCollection(livePreview: true);

    $component = Livewire::test(EntryForm::class, ['collection' => $collection, 'site' => 'indonesia']);

    $component->assertSee('Live Preview')
        ->assertSeeHtml('target="_blank"')
        ->assertSee($component->instance()->previewUrl, escape: false);
});

test('the preview route reports the cached version for live reload polling', function () {
    $this->actingAs(User::factory()->create());
    $collection = makeLivePreviewCollection(livePreview: true);

    $component = Livewire::test(EntryForm::class, ['collection' => $collection, 'site' => 'indonesia']);
    $token = $component->instance()->previewToken;

    $this->get(route('collections.preview', ['token' => $token, 'version' => 1]))
        ->assertOk()
        ->assertExactJson(['version' => 1]);

    $component->set('title', 'Edited title');

    $this->get(route('collections.preview', ['token' => $token, 'version' => 1]))
        ->assertExactJson(['version' => 2]);
});

test('the live preview button is not shown when live preview is off', function () {
    $this->actingAs(User::factory()->create());
    $collection = makeLivePreviewCollection(livePreview: false);

    Livewire::test(EntryForm::class, ['collection' => $collection, 'site' => 'indonesia'])
        ->assertDontSee('Live Preview');
});
