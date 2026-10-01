<?php

use App\Models\User;
use App\Modules\Sites\Livewire\SiteManager;
use App\Modules\Sites\Models\Site;
use App\Modules\Users\Models\Role;
use Livewire\Livewire;

test('sites page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin-panel/sites')->assertOk();
});

test('site can be created with additional attributes', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SiteManager::class)
        ->set('name', 'German')
        ->set('handle', 'german')
        ->set('url', 'https://example.de/')
        ->set('locale', 'de_DE')
        ->set('lang', 'de')
        ->set('customAttributes', [
            ['key' => 'native_name', 'value' => 'Deutsch'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $site = Site::sole();

    expect($site->name)->toBe('German');
    expect($site->handle)->toBe('german');
    expect($site->url)->toBe('https://example.de/');
    expect($site->locale)->toBe('de_DE');
    expect($site->lang)->toBe('de');
    expect($site->attributes)->toBe(['native_name' => 'Deutsch']);
    expect($site->order)->toBe(1);
});

test('sites are auto-ordered as they are added', function () {
    $this->actingAs(User::factory()->create());

    foreach (['english', 'german', 'french'] as $handle) {
        Livewire::test(SiteManager::class)
            ->set('name', ucfirst($handle))
            ->set('handle', $handle)
            ->set('url', "https://example.com/{$handle}")
            ->set('locale', 'en_US')
            ->set('lang', 'en')
            ->call('save');
    }

    expect(Site::orderBy('order')->pluck('handle')->all())->toBe(['english', 'german', 'french']);
});

test('site handle must be unique', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['name' => 'English', 'handle' => 'english', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    Livewire::test(SiteManager::class)
        ->set('name', 'English Two')
        ->set('handle', 'english')
        ->set('url', 'https://example.com')
        ->set('locale', 'en_US')
        ->set('lang', 'en')
        ->call('save')
        ->assertHasErrors(['handle']);
});

test('site can be updated and attributes round-trip through edit', function () {
    $this->actingAs(User::factory()->create());

    $site = Site::create([
        'name' => 'English', 'handle' => 'english', 'url' => 'https://example.com',
        'locale' => 'en_US', 'lang' => 'en', 'attributes' => ['native_name' => 'English'], 'order' => 1,
    ]);

    Livewire::test(SiteManager::class)
        ->call('edit', $site->id)
        ->assertSet('customAttributes.0.key', 'native_name')
        ->assertSet('customAttributes.0.value', 'English')
        ->set('name', 'American English')
        ->call('save')
        ->assertHasNoErrors();

    $site->refresh();

    expect($site->name)->toBe('American English');
    expect($site->attributes)->toBe(['native_name' => 'English']);
});

test('site can be deleted', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['name' => 'English', 'handle' => 'english', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);
    $site = Site::create(['name' => 'German', 'handle' => 'german', 'url' => 'https://example.de', 'locale' => 'de_DE', 'lang' => 'de', 'attributes' => [], 'order' => 2]);

    Livewire::test(SiteManager::class)->call('delete', $site->id);

    expect(Site::find($site->id))->toBeNull();
});

test('origin site cannot be deleted', function () {
    $this->actingAs(User::factory()->create());

    $origin = Site::create(['name' => 'English', 'handle' => 'english', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);
    Site::create(['name' => 'German', 'handle' => 'german', 'url' => 'https://example.de', 'locale' => 'de_DE', 'lang' => 'de', 'attributes' => [], 'order' => 2]);

    Livewire::test(SiteManager::class)->call('delete', $origin->id);

    expect(Site::find($origin->id))->not->toBeNull();
});

test('sites can be reordered', function () {
    $this->actingAs(User::factory()->create());

    $english = Site::create(['name' => 'English', 'handle' => 'english', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);
    $german = Site::create(['name' => 'German', 'handle' => 'german', 'url' => 'https://example.de', 'locale' => 'de_DE', 'lang' => 'de', 'attributes' => [], 'order' => 2]);

    Livewire::test(SiteManager::class)->call('moveDown', $english->id);

    expect($english->fresh()->order)->toBe(2);
    expect($german->fresh()->order)->toBe(1);
});

test('a user without "configure sites" permission does not see the add/edit/delete site controls', function () {
    Site::create(['name' => 'English', 'handle' => 'english', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(SiteManager::class)
        ->assertDontSeeHtml('wire:click="create"')
        ->assertDontSeeHtml('wire:click="moveUp(')
        ->assertDontSeeHtml('wire:click="moveDown(');
});

test('a user without "configure sites" permission still 403s if they call the mutating methods directly', function () {
    $site = Site::create(['name' => 'English', 'handle' => 'english', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(SiteManager::class)->call('delete', $site->id)->assertForbidden();
});

test('a user with "configure sites" permission sees the add/edit/delete site controls', function () {
    Site::create(['name' => 'English', 'handle' => 'english', 'url' => 'https://example.com', 'locale' => 'en_US', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure sites']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(SiteManager::class)->assertSeeHtml('wire:click="create"');
});
