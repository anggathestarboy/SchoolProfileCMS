<?php

use App\Models\User;
use App\Modules\Redirects\Models\Redirect;

test('an exact-match active redirect sends visitors to the target with its status code', function () {
    Redirect::create([
        'source' => '/test',
        'target' => '/admin-panel/dashboard',
        'status_code' => 301,
        'match_type' => 'exact',
        'is_active' => true,
    ]);

    $this->get('/test')
        ->assertRedirect('/admin-panel/dashboard')
        ->assertStatus(301)
        ->assertHeader('Cache-Control', 'must-revalidate, no-cache, no-store, private');

    expect(Redirect::sole()->hits)->toBe(1);
});

test('hits keep incrementing on repeat visits instead of the browser caching the 301', function () {
    Redirect::create([
        'source' => '/test',
        'target' => '/admin-panel/dashboard',
        'status_code' => 301,
        'match_type' => 'exact',
        'is_active' => true,
    ]);

    $this->get('/test');
    $this->get('/test');
    $this->get('/test');

    expect(Redirect::sole()->hits)->toBe(3);
});

test('an inactive redirect is ignored', function () {
    Redirect::create([
        'source' => '/inactive',
        'target' => '/somewhere',
        'status_code' => 301,
        'match_type' => 'exact',
        'is_active' => false,
    ]);

    $this->get('/inactive')->assertNotFound();
});

test('a prefix redirect with keep_path appends the remaining path to the target', function () {
    Redirect::create([
        'source' => '/blog',
        'target' => '/articles',
        'status_code' => 302,
        'match_type' => 'prefix',
        'is_active' => true,
        'keep_path' => true,
    ]);

    $this->get('/blog/my-post')
        ->assertRedirect('/articles/my-post')
        ->assertStatus(302);
});

test('a regex redirect with keep_path appends whatever the pattern did not consume', function () {
    Redirect::create([
        'source' => '#^/product-(\d+)#',
        'target' => '/items',
        'status_code' => 301,
        'match_type' => 'regex',
        'is_active' => true,
        'keep_path' => true,
    ]);

    $this->get('/product-42/reviews')->assertRedirect('/items/reviews');
});

test('an external redirect target is not resolved against the app URL', function () {
    Redirect::create([
        'source' => '/away',
        'target' => 'https://example.com/landing',
        'status_code' => 301,
        'match_type' => 'exact',
        'is_active' => true,
        'is_external' => true,
    ]);

    $this->get('/away')->assertRedirect('https://example.com/landing');
});

test('an external redirect target without a scheme is sent with https:// instead of staying on-site', function () {
    Redirect::create([
        'source' => '/aksata',
        'target' => 'www.anggara.xyz',
        'status_code' => 301,
        'match_type' => 'exact',
        'is_active' => true,
        'is_external' => true,
    ]);

    $this->get('/aksata')->assertRedirect('https://www.anggara.xyz');
});

test('a 410 status still sends visitors to the target via a client-side redirect, since no Location is possible on that code', function () {
    Redirect::create([
        'source' => '/discontinued',
        'target' => '/moved-here',
        'status_code' => 410,
        'match_type' => 'exact',
        'is_active' => true,
    ]);

    $response = $this->get('/discontinued');

    $response->assertStatus(410);
    $response->assertHeaderMissing('Location');
    $response->assertSee('/moved-here', false);
    $response->assertSee('window.location.replace', false);
    expect(Redirect::sole()->hits)->toBe(1);
});

test('a 451 status still sends visitors to the target via a client-side redirect, since no Location is possible on that code', function () {
    Redirect::create([
        'source' => '/blocked',
        'target' => '/moved-here',
        'status_code' => 451,
        'match_type' => 'exact',
        'is_active' => true,
    ]);

    $response = $this->get('/blocked');

    $response->assertStatus(451);
    $response->assertHeaderMissing('Location');
    $response->assertSee('/moved-here', false);
});

test('a redirect outside its start/end window is ignored', function () {
    Redirect::create([
        'source' => '/future',
        'target' => '/somewhere',
        'status_code' => 301,
        'match_type' => 'exact',
        'is_active' => true,
        'start_at' => now()->addDay(),
    ]);

    $this->get('/future')->assertNotFound();
});

test('redirects do not apply to admin panel routes', function () {
    Redirect::create([
        'source' => '/dashboard',
        'target' => '/somewhere-else',
        'status_code' => 301,
        'match_type' => 'exact',
        'is_active' => true,
    ]);

    $this->actingAs(User::factory()->create())
        ->get('/admin-panel/dashboard')
        ->assertOk();
});
