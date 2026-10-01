<?php

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\Fieldset;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Globals\Livewire\GlobalSetForm;
use App\Modules\Globals\Livewire\GlobalSetManager;
use App\Modules\Globals\Models\GlobalSet;
use App\Modules\Globals\Models\GlobalSetVariable;
use App\Modules\Sites\Models\Site;
use App\Modules\Users\Models\Role;
use Livewire\Livewire;

function makeSite(string $handle, int $order = 0): Site
{
    return Site::create([
        'handle' => $handle,
        'name' => ucfirst($handle),
        'url' => "https://{$handle}.example.com",
        'locale' => $handle,
        'lang' => $handle,
        'attributes' => [],
        'order' => $order,
    ]);
}

test('globals page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin-panel/globals')->assertOk();
});

test('global set can be created with an existing blueprint and gets an empty values row', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'site_name', 'display' => 'Site name', 'type' => 'text', 'required' => false],
    ]]]);

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Site Settings')
        ->set('handle', 'site_settings')
        ->set('createBlueprint', false)
        ->set('blueprint', 'site_settings')
        ->call('save')
        ->assertHasNoErrors();

    $globalSet = GlobalSet::sole();

    expect($globalSet->title)->toBe('Site Settings');
    expect($globalSet->settings['blueprint'])->toBe('site_settings');
    expect(GlobalSetVariable::where('handle', 'site_settings')->exists())->toBeTrue();
});

test('global set creation defaults to auto-creating its own blueprint, namespaced to the global set', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Site Settings')
        ->set('handle', 'site_settings')
        ->call('save')
        ->assertHasNoErrors();

    $globalSet = GlobalSet::sole();

    expect($globalSet->settings['blueprint'])->toBeNull();

    $blueprint = Blueprint::where('namespace', 'globals.site_settings')->sole();

    expect($blueprint->handle)->toBe('site_settings');
    expect($blueprint->data['fields'])->toBe([]);
    expect($globalSet->effectiveBlueprint()->id)->toBe($blueprint->id);
});

test('global set handle can be regenerated from the title', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Site Settings')
        ->call('regenerateHandle')
        ->assertSet('handle', 'site_settings');
});

test('global set handle rejects spaces and dashes', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Site Settings')
        ->set('handle', 'site settings')
        ->call('save')
        ->assertHasErrors(['handle']);

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Site Settings')
        ->set('handle', 'site-settings')
        ->call('save')
        ->assertHasErrors(['handle']);
});

test('global set handle must be unique', function () {
    $this->actingAs(User::factory()->create());

    GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => []]);

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Other')
        ->set('handle', 'site_settings')
        ->call('save')
        ->assertHasErrors(['handle']);
});

test('global set values can be filled in and saved through its blueprint fields', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'site_name', 'display' => 'Site name', 'type' => 'text', 'required' => true, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);

    $globalSet = GlobalSet::create([
        'title' => 'Site Settings',
        'handle' => 'site_settings',
        'settings' => ['blueprint' => 'site_settings'],
    ]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->set('data.site_name', 'Acme Inc')
        ->call('save')
        ->assertHasNoErrors();

    $variable = GlobalSetVariable::where('handle', 'site_settings')->sole();

    expect($variable->data)->toBe(['site_name' => 'Acme Inc']);
});

test('global set values require blueprint required fields', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'site_name', 'display' => 'Site name', 'type' => 'text', 'required' => true, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);

    $globalSet = GlobalSet::create([
        'title' => 'Site Settings',
        'handle' => 'site_settings',
        'settings' => ['blueprint' => 'site_settings'],
    ]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->set('data.site_name', '')
        ->call('save')
        ->assertHasErrors(['data.site_name'])
        ->assertSee('The Site name field is required.');
});

test('a global set field with a "show when" condition only renders once it matches', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'use_custom_logo', 'display' => 'Use Custom Logo', 'type' => 'toggle', 'required' => false, 'placeholder' => null, 'default' => false, 'instructions' => null, 'conditions' => null],
        ['handle' => 'logo_url', 'display' => 'Logo Url', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'conditions' => ['mode' => 'show', 'field' => 'use_custom_logo', 'operator' => 'equals', 'value' => 'true']],
    ]]]);

    $globalSet = GlobalSet::create([
        'title' => 'Site Settings',
        'handle' => 'site_settings',
        'settings' => ['blueprint' => 'site_settings'],
    ]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->assertDontSee('Logo Url')
        ->set('data.use_custom_logo', true)
        ->assertSee('Logo Url');
});

test('a field referenced by another field\'s condition is bound live, so the dependent field updates immediately', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'use_custom_logo', 'display' => 'Use Custom Logo', 'type' => 'toggle', 'required' => false, 'placeholder' => null, 'default' => false, 'instructions' => null, 'conditions' => null],
        ['handle' => 'logo_url', 'display' => 'Logo Url', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'conditions' => ['mode' => 'show', 'field' => 'use_custom_logo', 'operator' => 'equals', 'value' => 'true']],
        ['handle' => 'tagline', 'display' => 'Tagline', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'conditions' => null],
    ]]]);

    $globalSet = GlobalSet::create([
        'title' => 'Site Settings',
        'handle' => 'site_settings',
        'settings' => ['blueprint' => 'site_settings'],
    ]);

    $html = Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])->html();

    // "use_custom_logo" is a condition's target field, so it must sync
    // live — otherwise "Logo Url" only appears/disappears on some later,
    // unrelated request instead of immediately.
    expect($html)->toContain('wire:model.live="data.use_custom_logo"');
    // "tagline" isn't referenced by any condition, so it stays deferred.
    expect($html)->toContain('wire:model="data.tagline"');
    expect($html)->not->toContain('wire:model.live="data.tagline"');
});

test('creating a global set defaults to checking the first configured site', function () {
    $this->actingAs(User::factory()->create());
    makeSite('indonesia', 0);
    makeSite('english', 1);

    Livewire::test(GlobalSetManager::class)
        ->call('create')
        ->assertSet('sites', ['indonesia']);
});

test('global set with no sites checked stores values under a null locale, no switcher shown', function () {
    $this->actingAs(User::factory()->create());

    $globalSet = GlobalSet::create(['title' => 'Footer', 'handle' => 'footer', 'settings' => ['sites' => []]]);
    GlobalSetVariable::create(['handle' => 'footer', 'locale' => null, 'data' => []]);

    $component = Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet]);

    expect($component->get('activeSite'))->toBe('');
    $component->assertSee('This blueprint has no fields yet.');
});

test('global set with exactly one site checked has no switcher but still saves under that locale', function () {
    $this->actingAs(User::factory()->create());
    makeSite('indonesia');

    Blueprint::create(['namespace' => null, 'handle' => 'footer', 'data' => ['fields' => [
        ['handle' => 'copyright', 'display' => 'Copyright', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Footer', 'handle' => 'footer', 'settings' => ['blueprint' => 'footer', 'sites' => ['indonesia']]]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->assertSet('activeSite', 'indonesia')
        ->set('data.copyright', '© Acme')
        ->call('save')
        ->assertHasNoErrors();

    $variable = GlobalSetVariable::where('handle', 'footer')->where('locale', 'indonesia')->sole();
    expect($variable->data)->toBe(['copyright' => '© Acme']);
});

test('global set with two or more sites checked gets a language switcher keyed by ?site=', function () {
    $this->actingAs(User::factory()->create());
    makeSite('indonesia', 0);
    makeSite('english', 1);

    Blueprint::create(['namespace' => null, 'handle' => 'footer', 'data' => ['fields' => [
        ['handle' => 'copyright', 'display' => 'Copyright', 'type' => 'text', 'required' => false, 'localizable' => true, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Footer', 'handle' => 'footer', 'settings' => ['blueprint' => 'footer', 'sites' => ['indonesia', 'english']]]);

    // No ?site= yet: defaults to the first configured site.
    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->assertSet('activeSite', 'indonesia')
        ->set('data.copyright', 'Indonesian copyright')
        ->call('save')
        ->assertHasNoErrors();

    // Switching to ?site=english edits a separate value row.
    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet, 'activeSite' => 'english'])
        ->assertSet('activeSite', 'english')
        ->set('data.copyright', 'English copyright')
        ->call('save')
        ->assertHasNoErrors();

    expect(GlobalSetVariable::where('handle', 'footer')->where('locale', 'indonesia')->sole()->data)
        ->toBe(['copyright' => 'Indonesian copyright']);
    expect(GlobalSetVariable::where('handle', 'footer')->where('locale', 'english')->sole()->data)
        ->toBe(['copyright' => 'English copyright']);

    $component = Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet]);
    expect($component->get('siteLinks'))->toHaveCount(2);
});

test('non-localizable fields share one value from the default site across all languages', function () {
    $this->actingAs(User::factory()->create());
    makeSite('indonesia', 0);
    makeSite('english', 1);

    Blueprint::create(['namespace' => null, 'handle' => 'footer', 'data' => ['fields' => [
        ['handle' => 'company_name', 'display' => 'Company name', 'type' => 'text', 'required' => false, 'localizable' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
        ['handle' => 'tagline', 'display' => 'Tagline', 'type' => 'text', 'required' => false, 'localizable' => true, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Footer', 'handle' => 'footer', 'settings' => ['blueprint' => 'footer', 'sites' => ['indonesia', 'english']]]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->assertSet('activeSite', 'indonesia')
        ->set('data.company_name', 'Acme Inc')
        ->set('data.tagline', 'Solusi terbaik')
        ->call('save')
        ->assertHasNoErrors();

    // English editor shows Acme's shared value, but its own tagline.
    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet, 'activeSite' => 'english'])
        ->assertSet('data.company_name', 'Acme Inc')
        ->assertSet('data.tagline', '')
        ->set('data.tagline', 'Best solution')
        ->set('data.company_name', 'Should be ignored')
        ->call('save')
        ->assertHasNoErrors();

    $indonesian = GlobalSetVariable::where('handle', 'footer')->where('locale', 'indonesia')->sole();
    $english = GlobalSetVariable::where('handle', 'footer')->where('locale', 'english')->sole();

    expect($indonesian->data)->toBe(['company_name' => 'Acme Inc', 'tagline' => 'Solusi terbaik']);
    expect($english->data)->toBe(['company_name' => 'Acme Inc', 'tagline' => 'Best solution']);
});

test('link fields on a global set only offer entries of the active language', function () {
    $this->actingAs(User::factory()->create());
    makeSite('indonesia', 0);
    makeSite('english', 1);
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => []]);

    Blueprint::create(['namespace' => null, 'handle' => 'footer', 'data' => ['fields' => [
        ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'options' => []],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Footer', 'handle' => 'footer', 'settings' => ['blueprint' => 'footer', 'sites' => ['indonesia', 'english']]]);

    $indonesia = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'data' => ['title' => 'Beranda']]);
    $english = Entry::create(['site' => 'english', 'collection' => 'pages', 'data' => ['title' => 'Home']]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet, 'activeSite' => 'indonesia'])
        ->call('linkableEntries', [])
        ->assertReturned(fn ($entries) => array_column($entries, 'id') === [$indonesia->id]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet, 'activeSite' => 'english'])
        ->call('linkableEntries', [])
        ->assertReturned(fn ($entries) => array_column($entries, 'id') === [$english->id]);
});

test('a link field on a non-default language shows the default language\'s entry as its translated sibling', function () {
    $this->actingAs(User::factory()->create());
    makeSite('indonesia', 0);
    makeSite('english', 1);
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => []]);

    Blueprint::create(['namespace' => null, 'handle' => 'footer', 'data' => ['fields' => [
        ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'required' => false, 'localizable' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'options' => []],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Footer', 'handle' => 'footer', 'settings' => ['blueprint' => 'footer', 'sites' => ['indonesia', 'english']]]);

    $indonesia = Entry::create(['site' => 'indonesia', 'collection' => 'pages', 'data' => ['title' => 'Beranda']]);
    $english = Entry::create(['site' => 'english', 'collection' => 'pages', 'origin_id' => $indonesia->id, 'data' => ['title' => 'Home']]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->set('data.cta', "entry::{$indonesia->id}")
        ->call('save')
        ->assertHasNoErrors();

    // English shows the same content as its translated entry, not the raw
    // default-language reference; saving it untouched keeps the shared
    // value following the default language.
    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet, 'activeSite' => 'english'])
        ->assertSet('data.cta', "entry::{$english->id}")
        ->call('save')
        ->assertHasNoErrors();

    $englishVariable = GlobalSetVariable::where('handle', 'footer')->where('locale', 'english')->sole();
    expect($englishVariable->data)->toBe(['cta' => "entry::{$indonesia->id}"]);
});

test('global set can be deleted along with its values', function () {
    $this->actingAs(User::factory()->create());

    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => []]);
    GlobalSetVariable::create(['handle' => 'site_settings', 'data' => []]);

    Livewire::test(GlobalSetManager::class)->call('delete', $globalSet->id);

    expect(GlobalSet::find($globalSet->id))->toBeNull();
    expect(GlobalSetVariable::where('handle', 'site_settings')->exists())->toBeFalse();
});

test('saving a global set with multiple sites checked creates one values row per site', function () {
    $this->actingAs(User::factory()->create());
    makeSite('indonesia', 0);
    makeSite('english', 1);

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Footer')
        ->set('handle', 'footer')
        ->set('sites', ['indonesia', 'english'])
        ->call('save')
        ->assertHasNoErrors();

    $globalSet = GlobalSet::sole();

    expect($globalSet->settings['sites'])->toBe(['indonesia', 'english']);
    expect(GlobalSetVariable::where('handle', 'footer')->pluck('locale')->sort()->values()->all())
        ->toBe(['english', 'indonesia']);
});

test('global set sites must reference existing site handles', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Footer')
        ->set('handle', 'footer')
        ->set('sites', ['does-not-exist'])
        ->call('save')
        ->assertHasErrors(['sites.0']);
});

test('a blueprint tab with handle "sidebar" renders its fields in the global set form\'s right-hand sidebar column', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => [
        'tabs' => [
            ['handle' => 'main', 'display' => 'Main'],
            ['handle' => 'sidebar', 'display' => 'Sidebar'],
        ],
        'fields' => [
            ['handle' => 'site_name', 'display' => 'Site name', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'tab' => 'main'],
            ['handle' => 'seo_title', 'display' => 'Seo Title', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'tab' => 'sidebar'],
        ],
    ]]);

    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->assertSee('Site name')
        ->assertSee('Seo Title');
});

test('a fieldset field renders its referenced fields in the global set form', function () {
    $this->actingAs(User::factory()->create());

    Fieldset::create(['handle' => 'socials', 'data' => ['fields' => [
        ['handle' => 'twitter_url', 'display' => 'Twitter URL', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
        ['handle' => 'linkedin_url', 'display' => 'LinkedIn URL', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);
    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'socials', 'display' => 'Socials', 'type' => 'fieldset', 'options' => 'socials'],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->assertSee('Twitter URL')
        ->assertSee('LinkedIn URL');
});

test('a taxonomy field renders its terms and saves selections in the global set form', function () {
    $this->actingAs(User::factory()->create());

    Taxonomy::create(['handle' => 'categories', 'title' => 'Categories', 'sites' => []]);
    $news = TaxonomyTerm::create(['site' => 'indonesia', 'slug' => 'news', 'uri' => '/categories/news', 'taxonomy' => 'categories', 'data' => ['title' => 'News']]);
    $sports = TaxonomyTerm::create(['site' => 'indonesia', 'slug' => 'sports', 'uri' => '/categories/sports', 'taxonomy' => 'categories', 'data' => ['title' => 'Sports']]);

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'featured_categories', 'display' => 'Featured Categories', 'type' => 'taxonomy', 'options' => 'categories'],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->assertSee('News')
        ->assertSee('Sports')
        ->set('data.featured_categories', [$news->id, $sports->id])
        ->call('save')
        ->assertHasNoErrors();

    $variable = GlobalSetVariable::where('handle', 'site_settings')->sole();
    expect($variable->data)->toBe(['featured_categories' => [$news->id, $sports->id]]);
});

test('replicator rows in the global set form are enabled by default, can be toggled off, and can be duplicated', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'faqs', 'display' => 'FAQs', 'type' => 'replicator', 'required' => false, 'placeholder' => null, 'instructions' => null, 'replicator_fields' => [
            ['handle' => 'question', 'display' => 'Question', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
        ]],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);
    GlobalSetVariable::create(['handle' => 'site_settings', 'locale' => null, 'data' => ['faqs' => [
        ['question' => 'First'],
    ]]]);

    $component = Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet]);

    expect($component->get('data.faqs')[0]['_enabled'])->toBeTrue();

    $component->call('duplicateReplicatorRow', ['faqs'], 0);

    $rows = $component->get('data.faqs');
    expect($rows)->toHaveCount(2);
    expect($rows[1]['question'])->toBe('First');
    expect($rows[1]['_enabled'])->toBeTrue();

    $component->set('data.faqs.1._enabled', false)
        ->call('save')
        ->assertHasNoErrors();

    $variable = GlobalSetVariable::where('handle', 'site_settings')->sole();
    expect($variable->data)->toBe(['faqs' => [
        ['_enabled' => true, 'question' => 'First'],
        ['_enabled' => false, 'question' => 'First'],
    ]]);
});

test('a required replicator with no rows shows a validation error on save', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'faqs', 'display' => 'FAQs', 'type' => 'replicator', 'required' => true, 'placeholder' => null, 'instructions' => null, 'replicator_fields' => [
            ['handle' => 'question', 'display' => 'Question', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
        ]],
    ]]]);

    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->call('save')
        ->assertHasErrors(['data.faqs'])
        ->assertSee('The FAQs field is required.');
});

test('creating a global set requires the configure globals permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetManager::class)->call('create')->assertStatus(403);
});

test('a user with configure globals permission can create a global set', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure globals']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetManager::class)
        ->call('create')
        ->set('title', 'Site Settings')
        ->set('handle', 'site_settings')
        ->call('save')
        ->assertHasNoErrors();

    expect(GlobalSet::where('handle', 'site_settings')->exists())->toBeTrue();
});

test('editing a global set requires the configure globals permission', function () {
    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetManager::class)->call('edit', $globalSet->id)->assertStatus(403);
});

test('a user with configure globals permission can edit a global set', function () {
    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure globals']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetManager::class)
        ->call('edit', $globalSet->id)
        ->assertSet('handle', 'site_settings');
});

test('saving a global set definition requires the configure globals permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetManager::class)
        ->set('title', 'Site Settings')
        ->set('handle', 'site_settings')
        ->call('save')
        ->assertStatus(403);
});

test('deleting a global set requires the configure globals permission', function () {
    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetManager::class)->call('delete', $globalSet->id)->assertStatus(403);
});

test('a user with configure globals permission can delete a global set', function () {
    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => []]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure globals']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetManager::class)->call('delete', $globalSet->id);

    expect(GlobalSet::find($globalSet->id))->toBeNull();
});

test('viewing a global set form requires the view globals permission', function () {
    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'site_name', 'display' => 'Site name', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);
    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])->assertStatus(403);
});

test('a user with view globals permission can see the global set form', function () {
    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'site_name', 'display' => 'Site name', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);
    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'view site_settings globals']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])->assertOk();
});

test('saving global set values requires the edit globals permission', function () {
    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'site_name', 'display' => 'Site name', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);
    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view site_settings globals']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->set('data.site_name', 'Acme Inc')
        ->call('save')
        ->assertStatus(403);
});

test('a user with edit globals permission can save global set values', function () {
    Blueprint::create(['namespace' => null, 'handle' => 'site_settings', 'data' => ['fields' => [
        ['handle' => 'site_name', 'display' => 'Site name', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
    ]]]);
    $globalSet = GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site_settings', 'settings' => ['blueprint' => 'site_settings']]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'view site_settings globals', 'edit site_settings globals']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(GlobalSetForm::class, ['globalSet' => $globalSet])
        ->set('data.site_name', 'Acme Inc')
        ->call('save')
        ->assertHasNoErrors();

    $variable = GlobalSetVariable::where('handle', 'site_settings')->sole();
    expect($variable->data)->toBe(['site_name' => 'Acme Inc']);
});
