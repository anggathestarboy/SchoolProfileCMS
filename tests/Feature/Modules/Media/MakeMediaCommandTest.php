<?php

use App\Modules\Media\Models\AssetContainer;

test('creates a container with the given title and type', function () {
    $this->artisan('make:media', ['title' => 'Product Images', '--type' => 'public'])
        ->assertExitCode(0);

    $container = AssetContainer::where('handle', 'product_images')->first();

    expect($container)->not->toBeNull()
        ->and($container->title)->toBe('Product Images')
        ->and($container->disk)->toBe('public');
});

test('private type maps to the local disk', function () {
    $this->artisan('make:media', ['title' => 'Secrets', '--type' => 'private'])
        ->assertExitCode(0);

    expect(AssetContainer::where('handle', 'secrets')->value('disk'))->toBe('local');
});

test('prompts interactively when title and type are omitted', function () {
    $this->artisan('make:media')
        ->expectsQuestion('Container title', 'Team Docs')
        ->expectsChoice('Container type', 'private', ['public', 'private'])
        ->assertExitCode(0);

    expect(AssetContainer::where('handle', 'team_docs')->value('disk'))->toBe('local');
});

test('rejects a title that slugs to an already-used handle', function () {
    AssetContainer::create(['handle' => 'assets', 'title' => 'Assets', 'disk' => 'public']);

    $this->artisan('make:media')
        ->expectsQuestion('Container title', 'Assets')
        ->expectsOutputToContain('already exists')
        ->expectsQuestion('Container title', 'Assets V2')
        ->expectsChoice('Container type', 'public', ['public', 'private'])
        ->assertExitCode(0);

    expect(AssetContainer::where('handle', 'assets_v2')->exists())->toBeTrue();
});
