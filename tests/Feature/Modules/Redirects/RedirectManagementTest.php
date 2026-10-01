<?php

use App\Models\User;
use App\Modules\Redirects\Livewire\RedirectManager;
use App\Modules\Redirects\Models\Redirect;
use Livewire\Livewire;

function makeRedirect(array $overrides = []): Redirect
{
    return Redirect::create(array_merge([
        'source' => '/old-path',
        'target' => '/new-path',
        'status_code' => 301,
        'match_type' => 'exact',
        'is_active' => true,
        'is_external' => false,
        'keep_path' => true,
    ], $overrides));
}

test('redirect manager page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin-panel/redirect-manager')->assertOk();
});

test('viewing a redirect fills the form with its current values', function () {
    $this->actingAs(User::factory()->create());

    $redirect = makeRedirect(['hits' => 42]);

    Livewire::test(RedirectManager::class)
        ->call('view', $redirect->id)
        ->assertSet('source', '/old-path')
        ->assertSet('target', '/new-path')
        ->assertSet('status_code', 301)
        ->assertSet('is_active', true)
        ->assertSet('hits', 42);
});

test('a redirect can be updated, including its toggles', function () {
    $this->actingAs(User::factory()->create());

    $redirect = makeRedirect();

    Livewire::test(RedirectManager::class)
        ->call('view', $redirect->id)
        ->set('target', '/updated-path')
        ->set('status_code', 302)
        ->set('is_active', false)
        ->set('is_external', true)
        ->set('keep_path', false)
        ->call('save')
        ->assertHasNoErrors();

    $redirect->refresh();

    expect($redirect->target)->toBe('/updated-path');
    expect($redirect->status_code)->toBe(302);
    expect($redirect->is_active)->toBeFalse();
    expect($redirect->is_external)->toBeTrue();
    expect($redirect->keep_path)->toBeFalse();
});

test('saving fails validation when the source collides with another redirect', function () {
    $this->actingAs(User::factory()->create());

    makeRedirect(['source' => '/taken']);
    $redirect = makeRedirect(['source' => '/free']);

    Livewire::test(RedirectManager::class)
        ->call('view', $redirect->id)
        ->set('source', '/taken')
        ->call('save')
        ->assertHasErrors(['source']);
});

test('a redirect can be deleted', function () {
    $this->actingAs(User::factory()->create());

    $redirect = makeRedirect();

    Livewire::test(RedirectManager::class)
        ->call('delete', $redirect->id);

    expect(Redirect::find($redirect->id))->toBeNull();
});

test('a new redirect can be created', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(RedirectManager::class)
        ->call('create')
        ->assertSet('editingId', null)
        ->assertSet('status_code', 301)
        ->set('source', '/brand-new')
        ->set('target', '/somewhere-else')
        ->call('save')
        ->assertHasNoErrors();

    $redirect = Redirect::where('source', '/brand-new')->sole();
    expect($redirect->target)->toBe('/somewhere-else');
    expect($redirect->status_code)->toBe(301);
});

test('clearing start/end date fields saves as null instead of an empty string, and other fields in the same save still persist', function () {
    $this->actingAs(User::factory()->create());

    $redirect = makeRedirect(['start_at' => now(), 'end_at' => now()->addDay()]);

    Livewire::test(RedirectManager::class)
        ->call('view', $redirect->id)
        ->set('start_at', '')
        ->set('end_at', '')
        ->set('is_active', false)
        ->set('match_type', 'prefix')
        ->call('save')
        ->assertHasNoErrors();

    $redirect->refresh();

    expect($redirect->start_at)->toBeNull();
    expect($redirect->end_at)->toBeNull();
    expect($redirect->is_active)->toBeFalse();
    expect($redirect->match_type)->toBe('prefix');
});

test('status code accepts 410 and 451 alongside the redirect codes', function () {
    $this->actingAs(User::factory()->create());

    $redirect = makeRedirect();

    Livewire::test(RedirectManager::class)
        ->call('view', $redirect->id)
        ->set('status_code', 410)
        ->call('save')
        ->assertHasNoErrors();

    expect($redirect->refresh()->status_code)->toBe(410);

    Livewire::test(RedirectManager::class)
        ->call('view', $redirect->id)
        ->set('status_code', 451)
        ->call('save')
        ->assertHasNoErrors();

    expect($redirect->refresh()->status_code)->toBe(451);
});

test('switching match type to exact forces keep_path off and hides its toggle, prefix/regex show it', function () {
    $this->actingAs(User::factory()->create());

    $redirect = makeRedirect(['match_type' => 'prefix', 'keep_path' => true]);

    Livewire::test(RedirectManager::class)
        ->call('view', $redirect->id)
        ->assertSee('Keep path')
        ->set('match_type', 'exact')
        ->assertSet('keep_path', false)
        ->assertDontSee('Keep path')
        ->call('save')
        ->assertHasNoErrors();

    expect($redirect->refresh())
        ->match_type->toBe('exact')
        ->keep_path->toBeFalse();
});

test('keep_path stays forced off when saving an exact-match redirect even without touching match_type', function () {
    $this->actingAs(User::factory()->create());

    // Legacy/edge-case data: exact match that somehow still has keep_path true.
    $redirect = makeRedirect(['match_type' => 'exact', 'keep_path' => true]);

    Livewire::test(RedirectManager::class)
        ->call('view', $redirect->id)
        ->set('target', '/still-exact')
        ->call('save')
        ->assertHasNoErrors();

    expect($redirect->refresh()->keep_path)->toBeFalse();
});

test('redirects can be searched by source or target', function () {
    $this->actingAs(User::factory()->create());

    makeRedirect(['source' => '/blog-old', 'target' => '/blog']);
    makeRedirect(['source' => '/shop-old', 'target' => '/store']);

    Livewire::test(RedirectManager::class)
        ->set('search', 'blog')
        ->assertSee('/blog-old')
        ->assertDontSee('/shop-old')
        ->set('search', 'store')
        ->assertSee('/shop-old')
        ->assertDontSee('/blog-old');
});
