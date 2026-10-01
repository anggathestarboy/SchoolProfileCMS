<?php

use App\Modules\Blueprints\Models\Blueprint;

test('blueprints:create-homepage creates the Homepage blueprint for the pages collection', function () {
    $this->artisan('blueprints:create-homepage')->assertSuccessful();

    $blueprint = Blueprint::where('namespace', 'collections.pages')->where('handle', 'homepage')->sole();

    expect($blueprint->data['template'])->toBe('pages.static.homepage');

    $fieldHandles = collect($blueprint->data['fields'])->pluck('handle')->all();
    expect($fieldHandles)->toBe(['heading', 'subheading', 'content']);

    $heading = collect($blueprint->data['fields'])->firstWhere('handle', 'heading');
    expect($heading['type'])->toBe('text');
    expect($heading['required'])->toBeTrue();

    $content = collect($blueprint->data['fields'])->firstWhere('handle', 'content');
    expect($content['type'])->toBe('bard');
});

test('blueprints:create-homepage skips if a homepage blueprint already exists', function () {
    Blueprint::create(['namespace' => 'collections.pages', 'handle' => 'homepage', 'data' => ['fields' => []]]);

    $this->artisan('blueprints:create-homepage')->assertSuccessful();

    expect(Blueprint::where('namespace', 'collections.pages')->where('handle', 'homepage')->count())->toBe(1);
});
