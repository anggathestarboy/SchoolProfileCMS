<?php

use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;

function makeScheduledEntryFixtures(): Collection
{
    return Collection::create(['handle' => 'blog', 'title' => 'Blog', 'settings' => []]);
}

test('entries:publish-scheduled publishes a scheduled entry once its date has passed', function () {
    makeScheduledEntryFixtures();

    $entry = Entry::create([
        'site' => 'indonesia', 'collection' => 'blog', 'status' => 'scheduled', 'published' => false,
        'date' => now()->subMinute()->format('Y-m-d H:i'), 'data' => [],
    ]);

    $this->artisan('entries:publish-scheduled')->assertSuccessful();

    $entry->refresh();
    expect($entry->status)->toBe('published');
    expect($entry->published)->toBeTrue();
});

test('entries:publish-scheduled leaves a still-future scheduled entry alone', function () {
    makeScheduledEntryFixtures();

    $entry = Entry::create([
        'site' => 'indonesia', 'collection' => 'blog', 'status' => 'scheduled', 'published' => false,
        'date' => now()->addDay()->format('Y-m-d H:i'), 'data' => [],
    ]);

    $this->artisan('entries:publish-scheduled')->assertSuccessful();

    expect($entry->fresh()->status)->toBe('scheduled');
});

test('entries:publish-scheduled leaves non-scheduled entries untouched', function () {
    makeScheduledEntryFixtures();

    $draft = Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'draft', 'date' => now()->subMinute()->format('Y-m-d H:i'), 'data' => []]);
    $published = Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'published' => true, 'date' => now()->subMinute()->format('Y-m-d H:i'), 'data' => []]);

    $this->artisan('entries:publish-scheduled')->assertSuccessful();

    expect($draft->fresh()->status)->toBe('draft');
    expect($published->fresh()->status)->toBe('published');
});
