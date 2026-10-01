<?php

use App\Modules\Sites\Models\Site;

test('sites:create-default creates a site from the given options', function () {
    $this->artisan('sites:create-default', [
        '--name' => 'My Site',
        '--handle' => 'main',
    ])->assertSuccessful();

    $site = Site::sole();
    expect($site->handle)->toBe('main');
    expect($site->name)->toBe('My Site');
    expect($site->url)->toBe('/');
    expect($site->locale)->toBe('en');
    expect($site->lang)->toBe('en');
});

test('sites:create-default skips if a site already exists', function () {
    Site::create(['handle' => 'existing', 'name' => 'Existing', 'url' => 'https://example.com', 'locale' => 'en', 'lang' => 'en', 'attributes' => [], 'order' => 1]);

    $this->artisan('sites:create-default', [
        '--name' => 'My Site',
        '--handle' => 'main',
    ])->assertSuccessful();

    expect(Site::count())->toBe(1);
    expect(Site::sole()->handle)->toBe('existing');
});

test('sites:create-default prompts for name and handle when not given', function () {
    $this->artisan('sites:create-default')
        ->expectsQuestion('Default site name', 'My Site')
        ->expectsQuestion('Default site handle', 'main')
        ->assertSuccessful();

    $site = Site::sole();
    expect($site->handle)->toBe('main');
    expect($site->name)->toBe('My Site');
});

test('sites:create-default re-prompts for a blank handle', function () {
    $this->artisan('sites:create-default')
        ->expectsQuestion('Default site name', 'My Site')
        ->expectsQuestion('Default site handle', '')
        ->expectsOutput('Enter a site handle.')
        ->expectsQuestion('Default site handle', 'main')
        ->assertSuccessful();

    expect(Site::sole()->handle)->toBe('main');
});
