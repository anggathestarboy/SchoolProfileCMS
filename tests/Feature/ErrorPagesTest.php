<?php

use App\Models\User;
use App\Modules\Sites\Models\Site;

test('a 404 under the CMS route renders the admin-styled error page', function () {
    $response = $this->get(config('cms.route').'/this-route-does-not-exist');

    $response->assertNotFound();
    $response->assertSee("This admin page doesn't exist");
});

test('a 404 under the CMS route shows the admin sidebar when logged in', function () {
    $this->actingAs(User::factory()->create());

    $response = $this->get(config('cms.route').'/this-route-does-not-exist');

    $response->assertNotFound();
    $response->assertSee("This admin page doesn't exist");
    $response->assertSee('Visit Site');
});

test('a 404 on the public site renders the site-styled error page', function () {
    $response = $this->get('/this-page-does-not-exist');

    $response->assertNotFound();
    $response->assertSee("We couldn't find that page");
});

test('a 404 under a language site keeps that site selected in the language switcher', function () {
    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => '/', 'locale' => 'id', 'lang' => 'id', 'attributes' => [], 'order' => 1]);
    Site::create(['handle' => 'english', 'name' => 'English', 'url' => '/en', 'locale' => 'en', 'lang' => 'en', 'attributes' => [], 'order' => 2]);

    $this->get('/en/missing')->assertNotFound()->assertSee('<option value="/en" selected', false);
});
