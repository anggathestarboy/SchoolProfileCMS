<?php

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Sites\Models\Site;
use App\Modules\SiteSettings\Livewire\SiteSettingsManager;
use App\Modules\SiteSettings\Models\SiteSetting;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

test('robots.txt is served publicly as plain text', function () {
    SiteSetting::current()->update(['robots_txt' => "User-agent: *\nAllow: /"]);

    $this->get('/robots.txt')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Allow: /');
});

test('llms.txt is served publicly as plain text', function () {
    SiteSetting::current()->update(['llms_txt' => '# My Site']);

    $this->get('/llms.txt')
        ->assertOk()
        ->assertSee('# My Site');
});

test('robots.txt and llms.txt are empty but not missing when never configured', function () {
    $this->get('/robots.txt')->assertOk();
    $this->get('/llms.txt')->assertOk();
});

test('site settings can be saved from the admin form', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SiteSettingsManager::class)
        ->set('robotsTxt', "User-agent: *\nDisallow: /admin-panel")
        ->set('llmsTxt', '# Acme')
        ->call('addSchemaBlock')
        ->set('schemaBlocks.0.name', 'Acme Inc.')
        ->set('schemaBlocks.0.alternate_name', 'Acme')
        ->set('schemaBlocks.0.description', 'We make things.')
        ->set('schemaBlocks.0.same_as', ['https://twitter.com/acme', ''])
        ->call('save')
        ->assertHasNoErrors();

    $settings = SiteSetting::current();

    expect($settings->robots_txt)->toBe("User-agent: *\nDisallow: /admin-panel");
    expect($settings->llms_txt)->toBe('# Acme');
    expect($settings->schema[0]['name'])->toBe('Acme Inc.');
    // The blank second entry is dropped, not saved as an empty string.
    expect($settings->schema[0]['same_as'])->toBe(['https://twitter.com/acme']);
});

test('multiple schema blocks can be added, edited, and removed independently', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SiteSettingsManager::class)
        ->call('addSchemaBlock')
        ->call('addSchemaBlock')
        ->set('schemaBlocks.0.name', 'Acme Inc.')
        ->set('schemaBlocks.1.type', 'WebSite')
        ->set('schemaBlocks.1.name', 'Acme Site')
        ->call('save')
        ->assertHasNoErrors();

    $settings = SiteSetting::current();

    expect($settings->schema)->toHaveCount(2);
    expect($settings->schema[0]['type'])->toBe('Organization');
    expect($settings->schema[1]['type'])->toBe('WebSite');

    Livewire::test(SiteSettingsManager::class)
        ->call('removeSchemaBlock', 0)
        ->call('save')
        ->assertHasNoErrors();

    expect(SiteSetting::current()->schema)->toHaveCount(1);
    expect(SiteSetting::current()->schema[0]['type'])->toBe('WebSite');
});

test('the fill-default buttons populate a starter robots.txt and llms.txt', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SiteSettingsManager::class)
        ->call('fillDefaultRobots')
        ->assertSet('robotsTxt', fn (string $value) => str_contains($value, 'User-agent: *'))
        ->call('fillDefaultLlms')
        ->assertSet('llmsTxt', fn (string $value) => str_starts_with($value, '#'));
});

test('picking a logo resolves its url via the shared asset picker', function () {
    $this->actingAs(User::factory()->create());

    $container = AssetContainer::create(['handle' => 'assets', 'title' => 'Assets', 'disk' => 'public', 'settings' => []]);
    $asset = AssetMeta::create(['container' => $container->handle, 'folder' => '', 'basename' => 'logo.png', 'filename' => 'logo', 'extension' => 'png', 'path' => 'logo.png', 'meta' => []]);

    Livewire::test(SiteSettingsManager::class)
        ->call('addSchemaBlock')
        ->call('schemaAssetsSelected', ['__site_settings_logo__', 0], [$asset->id])
        ->assertSet('schemaBlocks.0.logo', $asset->url);
});

test('a configured organization schema is injected as json-ld on public pages', function () {
    $path = resource_path('views/pages/dynamic/site_settings_test_schema.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<x-layouts::site.layout :title="$data->title" :site="$site ?? null">{{ $data->title }}</x-layouts::site.layout>');

    try {
        SiteSetting::current()->update(['schema' => [
            'name' => 'Acme Inc.',
            'alternate_name' => null,
            'description' => null,
            'logo' => null,
            'same_as' => ['https://twitter.com/acme'],
        ]]);

        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => 'pages.dynamic.site_settings_test_schema', 'fields' => []]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'schema-test', 'uri' => 'schema-test', 'data' => ['title' => 'Schema Test'],
        ]);

        $this->get('/schema-test')
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('Acme Inc.', false)
            ->assertSee('https://twitter.com/acme', false);
    } finally {
        File::delete($path);
    }
});

test('no schema script is rendered until an organization name is set', function () {
    $path = resource_path('views/pages/dynamic/site_settings_test_no_schema.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<x-layouts::site.layout :title="$data->title" :site="$site ?? null">{{ $data->title }}</x-layouts::site.layout>');

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => 'pages.dynamic.site_settings_test_no_schema', 'fields' => []]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);

        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'no-schema-test', 'uri' => 'no-schema-test', 'data' => ['title' => 'No Schema'],
        ]);

        $this->get('/no-schema-test')->assertOk()->assertDontSee('application/ld+json', false);
    } finally {
        File::delete($path);
    }
});

test('generating llms.txt lists published entries from the selected collections, respecting the limit', function () {
    $this->actingAs(User::factory()->create());

    Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'https://example.id', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => ['route_mode' => 'single', 'route' => 'blog/{slug}']]);
    Collection::create(['title' => 'Pages', 'handle' => 'pages']);

    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'published', 'slug' => 'hello', 'data' => ['title' => 'Hello World']]);
    Entry::create(['site' => 'indonesia', 'collection' => 'blog', 'status' => 'draft', 'slug' => 'secret', 'data' => ['title' => 'Secret Draft']]);

    $component = Livewire::test(SiteSettingsManager::class)
        ->set('llmsCollections', ['blog'])
        ->set('llmsLimit', 1)
        ->call('generateLlms');

    $llms = $component->get('llmsTxt');

    expect($llms)->toContain('## Blog');
    expect($llms)->toContain('[Hello World](https://example.id/blog/hello)');
    // Drafts never show up, regardless of the limit.
    expect($llms)->not->toContain('Secret Draft');
    // Pages wasn't selected.
    expect($llms)->not->toContain('## Pages');
});

test('generating llms.txt lists taxonomy terms and appends the additional content', function () {
    $this->actingAs(User::factory()->create());

    Taxonomy::create(['handle' => 'categories', 'title' => 'Categories', 'sites' => [], 'settings' => []]);
    TaxonomyTerm::create(['taxonomy' => 'categories', 'site' => 'indonesia', 'slug' => 'news', 'data' => ['title' => 'News']]);

    $component = Livewire::test(SiteSettingsManager::class)
        ->set('llmsTaxonomies', ['categories'])
        ->set('llmsAdditionalContent', 'Contact us at hello@example.com.')
        ->call('generateLlms');

    $llms = $component->get('llmsTxt');

    expect($llms)->toContain('## Categories')->toContain('News')->toContain('Contact us at hello@example.com.');
});

test('select/deselect all toggles every available collection', function () {
    $this->actingAs(User::factory()->create());

    Collection::create(['title' => 'Blog', 'handle' => 'blog']);
    Collection::create(['title' => 'Pages', 'handle' => 'pages']);

    Livewire::test(SiteSettingsManager::class)
        ->call('toggleAllCollections')
        ->assertSet('llmsCollections', ['blog', 'pages'])
        ->call('toggleAllCollections')
        ->assertSet('llmsCollections', []);
});

test('the llms generator config is persisted and reloaded', function () {
    $this->actingAs(User::factory()->create());
    Collection::create(['title' => 'Blog', 'handle' => 'blog']);

    Livewire::test(SiteSettingsManager::class)
        ->set('llmsCollections', ['blog'])
        ->set('llmsLimit', 50)
        ->set('llmsAdditionalContent', 'Extra info.')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(SiteSettingsManager::class)
        ->assertSet('llmsCollections', ['blog'])
        ->assertSet('llmsLimit', 50)
        ->assertSet('llmsAdditionalContent', 'Extra info.');
});

test('a website schema type builds a WebSite json-ld block', function () {
    SiteSetting::current()->update(['schema' => [
        ['type' => 'WebSite', 'name' => 'Acme Site', 'description' => 'A site about acme.'],
    ]]);

    $data = SiteSetting::current()->structuredDataBlocks()[0];

    expect($data['@type'])->toBe('WebSite');
    expect($data['name'])->toBe('Acme Site');
    expect($data)->not->toHaveKey('sameAs');
});

test('a service schema type builds a Service json-ld block with a nested provider', function () {
    SiteSetting::current()->update(['schema' => [[
        'type' => 'Service',
        'name' => 'Acme Inc.',
        'description' => 'We build things.',
        'service_type' => 'Web Design',
        'area_served' => 'Indonesia',
    ]]]);

    $data = SiteSetting::current()->structuredDataBlocks()[0];

    expect($data['@type'])->toBe('Service');
    expect($data['serviceType'])->toBe('Web Design');
    expect($data['areaServed'])->toBe(['@type' => 'AdministrativeArea', 'name' => 'Indonesia']);
    expect($data['provider']['@type'])->toBe('Organization');
    expect($data['provider']['name'])->toBe('Acme Inc.');
});

test('an organization schema includes address and contact point only when filled in', function () {
    SiteSetting::current()->update(['schema' => [[
        'type' => 'Organization',
        'name' => 'Acme Inc.',
        'email' => 'hello@acme.com',
        'telephone' => '+62 812 0000 0000',
        'contact_type' => 'customer service',
        'address_street' => 'Jl. Sudirman',
        'address_locality' => 'Jakarta',
        'address_country' => 'ID',
    ]]]);

    $data = SiteSetting::current()->structuredDataBlocks()[0];

    expect($data['contactPoint'][0]['email'])->toBe('hello@acme.com');
    expect($data['address']['streetAddress'])->toBe('Jl. Sudirman');
});

test('a site can render more than one schema block at once', function () {
    SiteSetting::current()->update(['schema' => [
        ['type' => 'Organization', 'name' => 'Acme Inc.'],
        ['type' => 'WebSite', 'name' => 'Acme Site'],
    ]]);

    $data = SiteSetting::current()->structuredDataBlocks();

    expect($data)->toHaveCount(2);
    expect($data[0]['@type'])->toBe('Organization');
    expect($data[1]['@type'])->toBe('WebSite');
});

test('changing the schema type in the form swaps the visible fields', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SiteSettingsManager::class)
        ->set('activeTab', 'schema')
        ->call('addSchemaBlock')
        ->set('schemaBlocks.0.type', 'Service')
        ->assertSeeHtml('schemaBlocks.0.service_type')
        ->assertDontSeeHtml('schemaBlocks.0.address_street');
});

test('google analytics and search console integrations can be enabled and saved', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SiteSettingsManager::class)
        ->set('gaEnabled', true)
        ->set('gaMeasurementId', 'G-ABC123456')
        ->set('gscEnabled', true)
        ->set('gscVerificationCode', 'abcdefg1234567')
        ->call('save')
        ->assertHasNoErrors();

    $settings = SiteSetting::current();

    expect($settings->googleAnalyticsEnabled())->toBeTrue();
    expect($settings->googleAnalyticsMeasurementId())->toBe('G-ABC123456');
    expect($settings->googleSearchConsoleEnabled())->toBeTrue();
    expect($settings->googleSearchConsoleVerificationCode())->toBe('abcdefg1234567');
});

test('an enabled integration requires its id/code to be filled in', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(SiteSettingsManager::class)
        ->set('gaEnabled', true)
        ->set('gaMeasurementId', '')
        ->call('save')
        ->assertHasErrors(['gaMeasurementId']);
});

test('the google analytics snippet and search console meta tag only render when enabled', function () {
    $path = resource_path('views/pages/dynamic/site_settings_test_home.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '<x-layouts::site.layout>content</x-layouts::site.layout>');
    $template = 'pages.dynamic.site_settings_test_home';

    try {
        Site::create(['handle' => 'indonesia', 'name' => 'Indonesia', 'url' => 'http://127.0.0.1', 'locale' => 'id_ID', 'lang' => 'id', 'attributes' => [], 'order' => 0]);
        Blueprint::create(['namespace' => null, 'handle' => 'page', 'data' => ['template' => $template, 'fields' => []]]);
        Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => ['route_mode' => 'single', 'route' => '{slug}']]);
        Entry::create([
            'site' => 'indonesia', 'collection' => 'pages', 'blueprint' => 'page', 'status' => 'published',
            'slug' => 'ga-test', 'uri' => 'ga-test', 'data' => ['title' => 'GA Test'],
        ]);

        $this->get('/ga-test')->assertOk()->assertDontSee('googletagmanager.com', false)->assertDontSee('google-site-verification', false);

        SiteSetting::current()->update(['integrations' => [
            'ga_enabled' => true, 'ga_measurement_id' => 'G-ABC123456',
            'gsc_enabled' => true, 'gsc_verification_code' => 'abcdefg1234567',
        ]]);

        $this->get('/ga-test')
            ->assertOk()
            ->assertSee('googletagmanager.com/gtag/js?id=G-ABC123456', false)
            ->assertSee('<meta name="google-site-verification" content="abcdefg1234567" />', false);
    } finally {
        File::delete($path);
    }
});
