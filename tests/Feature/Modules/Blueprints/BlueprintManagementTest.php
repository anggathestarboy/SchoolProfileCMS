<?php

use App\Models\Taxonomy;
use App\Models\User;
use App\Modules\Blueprints\Livewire\BlueprintForm;
use App\Modules\Blueprints\Livewire\BlueprintManager;
use App\Modules\Blueprints\Livewire\FieldsetForm;
use App\Modules\Blueprints\Livewire\FieldsetManager;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\Fieldset;
use App\Modules\Collections\Models\Collection;
use App\Modules\Globals\Models\GlobalSet;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Users\Models\Role;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;

test('blueprints page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin-panel/blueprints')->assertOk();
});

test('blueprints are grouped by category with a heading per group', function () {
    $this->actingAs(User::factory()->create());

    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => []]]);
    Blueprint::create(['namespace' => 'globals.site_settings', 'handle' => 'site_settings', 'data' => ['fields' => []]]);
    Blueprint::create(['namespace' => 'taxonomies.categories', 'handle' => 'categories', 'data' => ['fields' => []]]);
    Blueprint::create(['namespace' => 'navigations.header', 'handle' => 'header', 'data' => ['fields' => []]]);
    Blueprint::create(['namespace' => null, 'handle' => 'loose', 'data' => ['fields' => []]]);

    $groups = Livewire::test(BlueprintManager::class)->instance()->blueprintGroups;

    expect($groups->get('collections')->pluck('handle')->all())->toBe(['post']);
    expect($groups->get('globals')->pluck('handle')->all())->toBe(['site_settings']);
    expect($groups->get('taxonomies')->pluck('handle')->all())->toBe(['categories']);
    expect($groups->get('navigations')->pluck('handle')->all())->toBe(['header']);
    expect($groups->get('other')->pluck('handle')->all())->toBe(['loose']);

    Livewire::test(BlueprintManager::class)
        ->assertSeeInOrder([__('Collections'), 'post', __('Globals'), 'site_settings', __('Taxonomies'), 'categories', __('Navigation'), 'header', __('Other'), 'loose']);
});

test('namespace options are derived from existing collections', function () {
    $this->actingAs(User::factory()->create());
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => []]);

    Livewire::test(BlueprintForm::class)
        ->assertSee('collections.blog')
        ->assertSee('collections.pages');
});

test('a non-editable schema tab indicator is shown for a collection with the schema tab enabled', function () {
    $this->actingAs(User::factory()->create());
    $collection = Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => ['schema_enabled' => true]]);
    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => []]]);

    Livewire::test(BlueprintForm::class, ['blueprint' => Blueprint::sole()])
        ->assertSet('namespace', 'collections.blog')
        ->assertSee(__('Schema'))
        ->assertSee(__('Code-managed'));
});

test('the schema tab indicator is hidden when the collection has not enabled it', function () {
    $this->actingAs(User::factory()->create());
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);
    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => []]]);

    Livewire::test(BlueprintForm::class, ['blueprint' => Blueprint::sole()])
        ->assertDontSee(__('Code-managed'));
});

test('a non-editable seo tab indicator is shown for a collection with the seo tab enabled', function () {
    $this->actingAs(User::factory()->create());
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => ['seo_enabled' => true]]);
    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => []]]);

    Livewire::test(BlueprintForm::class, ['blueprint' => Blueprint::sole()])
        ->assertSet('namespace', 'collections.blog')
        ->assertSee(__('SEO'))
        ->assertSee(__('Code-managed'));
});

test('namespace options also include existing global sets, and a globals-namespaced blueprint can be edited', function () {
    $this->actingAs(User::factory()->create());
    GlobalSet::create(['title' => 'Site Settings', 'handle' => 'site-settings', 'settings' => []]);

    Livewire::test(BlueprintForm::class)->assertSee('globals.site-settings');

    $blueprint = Blueprint::create(['namespace' => 'globals.site-settings', 'handle' => 'default', 'data' => ['fields' => []]]);

    Livewire::test(BlueprintForm::class, ['blueprint' => $blueprint])
        ->assertSet('namespace', 'globals.site-settings')
        ->set('fields', [
            ['handle' => 'site_name', 'display' => 'Site Name', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($blueprint->fresh()->data['fields'])->toHaveCount(1);
});

test('namespace options also include existing navigations, and a navigations-namespaced blueprint can be edited without its namespace being swapped', function () {
    $this->actingAs(User::factory()->create());
    Taxonomy::create(['title' => 'News Category', 'handle' => 'news_category']);
    Navigation::create(['title' => 'Header', 'handle' => 'header']);

    Livewire::test(BlueprintForm::class)->assertSee('navigations.header');

    $blueprint = Blueprint::create(['namespace' => 'navigations.header', 'handle' => 'header', 'data' => ['fields' => []]]);

    Livewire::test(BlueprintForm::class, ['blueprint' => $blueprint])
        ->assertSet('namespace', 'navigations.header')
        ->set('fields', [
            ['handle' => 'icon', 'display' => 'Icon', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($blueprint->fresh()->namespace)->toBe('navigations.header');
    expect($blueprint->fresh()->data['fields'])->toHaveCount(1);
});

test('a message is shown when no collections exist yet for the namespace picker', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->assertSee('No collections yet');
});

test('blueprint namespace must match an existing collection', function () {
    $this->actingAs(User::factory()->create());
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    Livewire::test(BlueprintForm::class)
        ->set('namespace', 'collections.does-not-exist')
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['namespace']);
});

test('blueprint handle rejects spaces and dashes', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'blog post')
        ->set('fields', [
            ['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['handle']);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'blog-post')
        ->set('fields', [
            ['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['handle']);
});

test('blueprint can be created with no namespace', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'seo')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(Blueprint::sole()->namespace)->toBeNull();
});

test('blueprint can be created with fields via the field builder', function () {
    $this->actingAs(User::factory()->create());
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    Livewire::test(BlueprintForm::class)
        ->set('namespace', 'collections.blog')
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => true, 'options' => '', 'placeholder' => 'Enter a headline', 'default' => '', 'validation' => 'max:255', 'instructions' => 'Shown in listings.', 'sometimes' => false, 'condition_mode' => 'always', 'condition_field' => '', 'condition_operator' => 'equals', 'condition_value' => ''],
            ['handle' => 'status', 'display' => 'Status', 'type' => 'select', 'required' => false, 'options' => 'draft, published, archived', 'placeholder' => '', 'default' => 'draft', 'validation' => '', 'instructions' => '', 'sometimes' => false, 'condition_mode' => 'always', 'condition_field' => '', 'condition_operator' => 'equals', 'condition_value' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    expect($blueprint->namespace)->toBe('collections.blog');
    expect($blueprint->handle)->toBe('post');
    expect($blueprint->data)->toBe([
        'template' => null,
        'fields_collapsed_by_default' => false,
        'tabs' => [
            ['handle' => 'main', 'display' => 'Main', 'sections' => [
                ['handle' => 'main', 'display' => 'Main'],
            ]],
        ],
        'fields' => [
            [
                'handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => true, 'localizable' => false,
                'placeholder' => 'Enter a headline', 'default' => null, 'validation' => 'max:255', 'instructions' => 'Shown in listings.',
                'validate_sometimes' => false, 'conditions' => null, 'tab' => 'main', 'section' => null,
            ],
            [
                'handle' => 'status', 'display' => 'Status', 'type' => 'select', 'required' => false, 'localizable' => false,
                'placeholder' => null, 'default' => 'draft', 'validation' => null, 'instructions' => null,
                'validate_sometimes' => false, 'conditions' => null,
                'options' => ['draft', 'published', 'archived'], 'tab' => 'main', 'section' => null,
            ],
        ],
    ]);
});

test('a bard field can have its toolbar buttons restricted', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'body', 'display' => 'Body', 'type' => 'bard', 'required' => false, 'options' => '', 'toolbar' => ['bold', 'italic']],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(Blueprint::sole()->data['fields'][0]['toolbar'])->toBe(['bold', 'italic']);
});

test('a bard field toolbar button must be a known key', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'body', 'display' => 'Body', 'type' => 'bard', 'required' => false, 'options' => '', 'toolbar' => ['not-a-real-button']],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.toolbar.0']);
});

test('blueprint can be assigned a template selected from resources/views/pages', function () {
    $this->actingAs(User::factory()->create());

    $path = resource_path('views/pages/dynamic/blueprint_test_home.blade.php');
    File::ensureDirectoryExists(dirname($path));
    File::put($path, '');

    try {
        Livewire::test(BlueprintForm::class)
            ->set('handle', 'home')
            ->set('template', 'pages.dynamic.blueprint_test_home')
            ->set('fields', [
                ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => ''],
            ])
            ->call('save')
            ->assertHasNoErrors();

        expect(Blueprint::sole()->data['template'])->toBe('pages.dynamic.blueprint_test_home');
    } finally {
        File::delete($path);
    }
});

test('blueprint template must be an existing file under resources/views/pages', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'home')
        ->set('template', 'pages.dynamic.does_not_exist')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['template']);
});

test('blueprint field can show when another field matches a value', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'type', 'display' => 'Type', 'type' => 'select', 'required' => false, 'options' => 'article, video', 'placeholder' => '', 'default' => '', 'validation' => '', 'instructions' => '', 'sometimes' => true, 'condition_mode' => 'always', 'condition_field' => '', 'condition_operator' => 'equals', 'condition_value' => ''],
            ['handle' => 'video_url', 'display' => 'Video Url', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '', 'validation' => 'url', 'instructions' => '', 'sometimes' => true, 'condition_mode' => 'show', 'condition_field' => 'type', 'condition_operator' => 'equals', 'condition_value' => 'video'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    expect($blueprint->data['fields'][1])->toBe([
        'handle' => 'video_url', 'display' => 'Video Url', 'type' => 'text', 'required' => false, 'localizable' => false,
        'placeholder' => null, 'default' => null, 'validation' => 'url', 'instructions' => null,
        'validate_sometimes' => true,
        'conditions' => ['mode' => 'show', 'field' => 'type', 'operator' => 'equals', 'value' => 'video'],
        'tab' => 'main', 'section' => null,
    ]);
});

test('blueprint field can hide when another field matches a value', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'status', 'display' => 'Status', 'type' => 'select', 'required' => false, 'options' => 'draft, published', 'placeholder' => '', 'default' => '', 'validation' => '', 'instructions' => '', 'sometimes' => false, 'condition_mode' => 'always', 'condition_field' => '', 'condition_operator' => 'equals', 'condition_value' => ''],
            ['handle' => 'draft_note', 'display' => 'Draft Note', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '', 'validation' => '', 'instructions' => '', 'sometimes' => false, 'condition_mode' => 'hide', 'condition_field' => 'status', 'condition_operator' => 'equals', 'condition_value' => 'published'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    expect($blueprint->data['fields'][1]['conditions'])->toBe([
        'mode' => 'hide', 'field' => 'status', 'operator' => 'equals', 'value' => 'published',
    ]);
});

test('blueprint condition requires a target field to be selected', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '', 'validation' => '', 'instructions' => '', 'sometimes' => false, 'condition_mode' => 'show', 'condition_field' => '', 'condition_operator' => 'equals', 'condition_value' => 'x'],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.condition_field']);
});

test('blueprint condition cannot reference the field itself', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '', 'validation' => '', 'instructions' => '', 'sometimes' => false, 'condition_mode' => 'show', 'condition_field' => 'headline', 'condition_operator' => 'equals', 'condition_value' => 'x'],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.condition_field']);
});

test('blueprint condition requires a value to compare against', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '', 'validation' => '', 'instructions' => '', 'sometimes' => false, 'condition_mode' => 'always', 'condition_field' => '', 'condition_operator' => 'equals', 'condition_value' => ''],
            ['handle' => 'subtitle', 'display' => 'Subtitle', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '', 'validation' => '', 'instructions' => '', 'sometimes' => false, 'condition_mode' => 'show', 'condition_field' => 'headline', 'condition_operator' => 'equals', 'condition_value' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.1.condition_value']);
});

test('condition inputs only appear once show or hide is selected, and reset when set back to always', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addField')
        ->set('fields.0.handle', 'title')
        ->set('fields.1.condition_mode', 'show')
        ->set('fields.1.condition_field', 'title')
        ->set('fields.1.condition_value', 'x')
        ->assertSet('fields.1.condition_field', 'title')
        ->set('fields.1.condition_mode', 'always')
        ->assertSet('fields.1.condition_field', '')
        ->assertSet('fields.1.condition_value', '');
});

test('blueprint rejects an invalid Laravel validation rule string', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '', 'validation' => 'not_a_real_rule', 'instructions' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.validation']);
});

test('blueprint rejects a validation rule string with disallowed characters', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '', 'validation' => 'max:255; DROP TABLE users;', 'instructions' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.validation']);
});

test('blueprint accepts a valid date default value', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'published_at', 'display' => 'Published At', 'type' => 'date', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => '2026-01-15', 'validation' => '', 'instructions' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    expect($blueprint->data['fields'][0]['default'])->toBe('2026-01-15');
    expect($blueprint->data['fields'][0]['placeholder'])->toBeNull();
});

test('blueprint rejects a malformed date default value', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'published_at', 'display' => 'Published At', 'type' => 'date', 'required' => false, 'options' => '', 'placeholder' => '', 'default' => 'not-a-date', 'validation' => '', 'instructions' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.default']);
});

test('changing a field type to date clears its placeholder', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('fields.0.placeholder', 'some placeholder')
        ->set('fields.0.type', 'date')
        ->assertSet('fields.0.placeholder', '');
});

test('blueprint handle must be unique within its namespace', function () {
    $this->actingAs(User::factory()->create());
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    Blueprint::create(['namespace' => 'collections.blog', 'handle' => 'post', 'data' => ['fields' => []]]);

    Livewire::test(BlueprintForm::class)
        ->set('namespace', 'collections.blog')
        ->set('handle', 'post')
        ->set('fields', [['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => false, 'options' => '']])
        ->call('save')
        ->assertHasErrors(['handle']);
});

test('blueprint requires at least one field', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [])
        ->call('save')
        ->assertHasErrors(['fields']);
});

test('blueprint field handles must be unique within the blueprint', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => ''],
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields']);
});

test('blueprint field handle must match the allowed format', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'Not Valid!', 'display' => '', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.handle']);
});

test('blueprint field label and handle are both required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => '', 'display' => '', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.handle', 'fields.0.display']);
});

test('a fieldset field does not require a label or handle, since it has no such inputs of its own', function () {
    $this->actingAs(User::factory()->create());

    Fieldset::create(['handle' => 'seo', 'data' => ['fields' => [
        ['handle' => 'meta_title', 'display' => 'Meta Title', 'type' => 'text', 'required' => false],
    ]]]);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => '', 'display' => '', 'type' => 'fieldset', 'required' => false, 'options' => 'seo'],
        ])
        ->call('save')
        ->assertHasNoErrors(['fields.0.handle', 'fields.0.display']);
});

test('blueprint field handle cannot be "title" — it is a reserved default field', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.handle']);
});

test('replicator field can be created with sub-fields', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    ['handle' => 'caption', 'display' => 'Caption', 'type' => 'text', 'required' => true],
                    ['handle' => 'featured', 'display' => 'Featured', 'type' => 'toggle', 'required' => false],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();
    $field = $blueprint->data['fields'][0];

    expect($field['type'])->toBe('replicator');
    expect($field['replicator_fields'])->toHaveCount(2);
    expect($field['replicator_fields'][0])->toMatchArray(['handle' => 'caption', 'type' => 'text', 'required' => true]);
});

test('replicator field can have a max row count, and it fails when its own max is less than min', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'max_items' => '3',
                'replicator_fields' => [
                    ['handle' => 'caption', 'display' => 'Caption', 'type' => 'text', 'required' => false],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['max_items'])->toBe(3);
});

test('replicator field can be set to collapse its rows by default', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'collapsed_by_default' => true,
                'replicator_fields' => [
                    ['handle' => 'caption', 'display' => 'Caption', 'type' => 'text', 'required' => false],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['collapsed_by_default'])->toBeTrue();
});

test('replicator field defaults to not collapsed and no row limit when left unset', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    ['handle' => 'caption', 'display' => 'Caption', 'type' => 'text', 'required' => false],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['collapsed_by_default'])->toBeFalse();
    expect($field['max_items'])->toBeNull();
});

test('replicator field requires at least one sub-field', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.replicator_fields']);
});

test('replicator sub-field handle cannot be "title"', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    ['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => false],
                ],
            ],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.replicator_fields.0.handle']);
});

test('replicator sub-field default value, placeholder and instructions round-trip through the blueprint builder', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    [
                        'handle' => 'caption', 'display' => 'Caption', 'type' => 'text', 'required' => false,
                        'placeholder' => 'Enter a caption', 'default' => 'Untitled', 'validation' => 'max:100', 'instructions' => 'Shown under the image',
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $sub = Blueprint::sole()->data['fields'][0]['replicator_fields'][0];

    expect($sub)->toMatchArray([
        'placeholder' => 'Enter a caption',
        'default' => 'Untitled',
        'validation' => 'max:100',
        'instructions' => 'Shown under the image',
    ]);
});

test('replicator sub-field with an invalid validation rule string fails', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    ['handle' => 'caption', 'display' => 'Caption', 'type' => 'text', 'required' => false, 'validation' => 'not_a_real_rule'],
                ],
            ],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.replicator_fields.0.validation']);
});

test('replicator field can contain another replicator, nested to any depth', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'sections', 'display' => 'Sections', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    [
                        'handle' => 'items', 'display' => 'Items', 'type' => 'replicator', 'required' => false, 'options' => '',
                        'replicator_fields' => [
                            ['handle' => 'label', 'display' => 'Label', 'type' => 'text', 'required' => true],
                        ],
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $sections = Blueprint::sole()->data['fields'][0];

    expect($sections['type'])->toBe('replicator');
    expect($sections['replicator_fields'][0]['type'])->toBe('replicator');
    expect($sections['replicator_fields'][0]['replicator_fields'][0])->toMatchArray([
        'handle' => 'label', 'type' => 'text', 'required' => true,
    ]);
});

test('nested replicator requires at least one sub-field, with an error at the correct depth', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'sections', 'display' => 'Sections', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    ['handle' => 'items', 'display' => 'Items', 'type' => 'replicator', 'required' => false, 'options' => ''],
                ],
            ],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.replicator_fields.0.replicator_fields']);
});

test('replicator sub-field can be a select with options and sometimes', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    [
                        'handle' => 'size', 'display' => 'Size', 'type' => 'select', 'required' => false,
                        'options' => 'small, medium, large', 'sometimes' => true,
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $sub = Blueprint::sole()->data['fields'][0]['replicator_fields'][0];

    expect($sub['options'])->toBe(['small', 'medium', 'large']);
    expect($sub['validate_sometimes'])->toBeTrue();
});

test('replicator sub-field can have a show-when condition scoped to its own sibling sub-fields', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    ['handle' => 'kind', 'display' => 'Kind', 'type' => 'select', 'required' => false, 'options' => 'image, video'],
                    [
                        'handle' => 'video_url', 'display' => 'Video Url', 'type' => 'text', 'required' => false,
                        'condition_mode' => 'show', 'condition_field' => 'kind', 'condition_operator' => 'equals', 'condition_value' => 'video',
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $sub = Blueprint::sole()->data['fields'][0]['replicator_fields'][1];

    expect($sub['conditions'])->toBe([
        'mode' => 'show',
        'field' => 'kind',
        'operator' => 'equals',
        'value' => 'video',
    ]);
});

test('replicator sub-field condition cannot reference a field outside the replicator', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            [
                'handle' => 'gallery', 'display' => 'Gallery', 'type' => 'replicator', 'required' => false, 'options' => '',
                'replicator_fields' => [
                    [
                        'handle' => 'video_url', 'display' => 'Video Url', 'type' => 'text', 'required' => false,
                        'condition_mode' => 'show', 'condition_field' => 'does_not_exist', 'condition_operator' => 'equals', 'condition_value' => 'video',
                    ],
                ],
            ],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.replicator_fields.0.condition_field']);
});

test('changing a field type resets its stale default value', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('fields.0.default', 'some text default')
        ->set('fields.0.type', 'toggle')
        ->assertSet('fields.0.default', '');
});

test('a toggle field stores its default value as a real boolean, not the string "false"', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'featured', 'display' => 'Featured', 'type' => 'toggle', 'required' => false, 'condition_mode' => 'always', 'default' => 'false'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['default'])->toBeFalse();
});

test('a toggle field defaulted to true round-trips correctly when editing the blueprint again', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'featured', 'display' => 'Featured', 'type' => 'toggle', 'required' => false, 'condition_mode' => 'always', 'default' => 'true'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    expect($blueprint->data['fields'][0]['default'])->toBeTrue();

    Livewire::test(BlueprintForm::class, ['blueprint' => $blueprint])
        ->assertSet('fields.0.default', 'true');
});

test('a legacy toggle field saved with a string default still edits correctly', function () {
    $this->actingAs(User::factory()->create());

    $blueprint = Blueprint::create([
        'handle' => 'post',
        'data' => [
            'tabs' => [['handle' => 'main', 'display' => 'Main', 'sections' => []]],
            'fields' => [
                ['handle' => 'featured', 'display' => 'Featured', 'type' => 'toggle', 'required' => false, 'default' => 'false'],
            ],
        ],
    ]);

    Livewire::test(BlueprintForm::class, ['blueprint' => $blueprint])
        ->assertSet('fields.0.default', 'false');
});

test('blueprint always has a reserved "main" tab that new fields default to', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->assertSet('tabs', [['handle' => 'main', 'display' => 'Main', 'sections' => [
            ['handle' => 'main', 'display' => 'Main'],
        ]]])
        ->assertSet('fields.0.tab', 'main')
        ->assertSet('fields.0.section', 'main');
});

test('blueprint can add a tab and assign a field to it', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('tabs', [
            ['handle' => 'main', 'display' => 'Main'],
            ['handle' => 'sidebar', 'display' => 'Sidebar'],
        ])
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => '', 'tab' => 'main'],
            ['handle' => 'seo_title', 'display' => 'Seo Title', 'type' => 'text', 'required' => false, 'options' => '', 'tab' => 'sidebar'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    expect($blueprint->data['tabs'])->toBe([
        ['handle' => 'main', 'display' => 'Main', 'sections' => []],
        ['handle' => 'sidebar', 'display' => 'Sidebar', 'sections' => []],
    ]);
    expect($blueprint->data['fields'][0]['tab'])->toBe('main');
    expect($blueprint->data['fields'][1]['tab'])->toBe('sidebar');
});

test('adding a field via a specific tab assigns it to that tab', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->set('tabs.1.handle', 'sidebar')
        ->call('addField', 'sidebar')
        ->assertSet('fields.1.tab', 'sidebar')
        ->assertSet('fields.0.tab', 'main');
});

test('moving a top-level field up or down only reorders within its own tab', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->set('tabs.1.handle', 'sidebar')
        ->set('fields.0.handle', 'main_one')
        ->call('addField', 'sidebar')
        ->set('fields.1.handle', 'sidebar_one')
        ->call('addField')
        ->set('fields.2.handle', 'main_two')
        ->call('addField', 'sidebar')
        ->set('fields.3.handle', 'sidebar_two')
        // fields: [0]=main_one(main) [1]=sidebar_one(sidebar) [2]=main_two(main) [3]=sidebar_two(sidebar)
        ->call('moveFieldDown', [1])
        ->assertSet('fields.1.handle', 'sidebar_two')
        ->assertSet('fields.3.handle', 'sidebar_one')
        ->assertSet('fields.0.handle', 'main_one')
        ->assertSet('fields.2.handle', 'main_two');
});

test('dragging a top-level field only reorders within its own tab/section group', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->set('tabs.1.handle', 'sidebar')
        ->set('fields.0.handle', 'main_one')
        ->call('addField', 'sidebar')
        ->set('fields.1.handle', 'sidebar_one')
        ->call('addField')
        ->set('fields.2.handle', 'main_two')
        ->call('addField', 'sidebar')
        ->set('fields.3.handle', 'sidebar_two');

    // fields: [0]=main_one(main) [1]=sidebar_one(sidebar) [2]=main_two(main) [3]=sidebar_two(sidebar)
    // Drag sidebar_one (by its stable _uid) to position 1 within the "sidebar" group.
    $component
        ->call('reorderFields', $component->get('fields.1._uid'), 1, json_encode(['tab' => 'sidebar', 'section' => '']))
        ->assertSet('fields.1.handle', 'sidebar_two')
        ->assertSet('fields.3.handle', 'sidebar_one')
        ->assertSet('fields.0.handle', 'main_one')
        ->assertSet('fields.2.handle', 'main_two');
});

test('dragging a top-level field into another section moves it there', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(BlueprintForm::class)
        ->call('addSection', 0)
        ->set('tabs.0.sections.0.handle', 'intro')
        ->call('addSection', 0)
        ->set('tabs.0.sections.1.handle', 'meta')
        ->set('fields.0.handle', 'ungrouped_one')
        ->call('addField', 'main', 'intro')
        ->set('fields.1.handle', 'intro_one')
        ->call('addField', 'main', 'meta')
        ->set('fields.2.handle', 'meta_one');

    // fields: [0]=ungrouped_one(no section) [1]=intro_one(intro) [2]=meta_one(meta)
    // Drag ungrouped_one (by its stable _uid) into the "meta" section, after meta_one.
    $component
        ->call('reorderFields', $component->get('fields.0._uid'), 1, json_encode(['tab' => 'main', 'section' => 'meta']))
        ->assertSet('fields.0.handle', 'meta_one')
        ->assertSet('fields.2.handle', 'ungrouped_one')
        ->assertSet('fields.2.section', 'meta');
});

test('dragging a top-level field into another tab moves it there', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->set('tabs.1.handle', 'seo_tools')
        ->set('fields.0.handle', 'main_one')
        ->call('addField', 'seo_tools')
        ->set('fields.1.handle', 'seo_one');

    // fields: [0]=main_one(main) [1]=seo_one(seo_tools)
    // Drag main_one (by its stable _uid) into the "seo_tools" tab, after seo_one.
    $component
        ->call('reorderFields', $component->get('fields.0._uid'), 1, json_encode(['tab' => 'seo_tools', 'section' => '']))
        ->assertSet('fields.0.tab', 'seo_tools')
        ->assertSet('fields.1.handle', 'main_one');
});

test('dragging a replicator sub-field reorders within that replicator only', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(BlueprintForm::class)
        ->set('fields.0.type', 'replicator')
        ->call('addReplicatorField', [0])
        ->set('fields.0.replicator_fields.0.handle', 'first')
        ->call('addReplicatorField', [0])
        ->set('fields.0.replicator_fields.1.handle', 'second')
        ->call('addReplicatorField', [0])
        ->set('fields.0.replicator_fields.2.handle', 'third');

    // Drag "first" (by its stable _uid) to the last position.
    $component
        ->call('reorderFields', $component->get('fields.0.replicator_fields.0._uid'), 2, json_encode(['parentPath' => [0]]))
        ->assertSet('fields.0.replicator_fields.0.handle', 'second')
        ->assertSet('fields.0.replicator_fields.1.handle', 'third')
        ->assertSet('fields.0.replicator_fields.2.handle', 'first');
});

test('reordering ignores an unknown field id', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('fields.0.handle', 'only_field')
        ->call('reorderFields', 'not-a-real-uid', 0, json_encode(['tab' => 'main', 'section' => '']))
        ->assertSet('fields.0.handle', 'only_field');
});

test('the "main" tab cannot be renamed or removed', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('tabs.0.handle', 'renamed')
        ->call('removeTab', 0)
        ->assertCount('tabs', 1)
        ->set('handle', 'post')
        ->set('fields.0.handle', 'headline')
        ->set('fields.0.display', 'Headline')
        ->call('save')
        ->assertHasNoErrors();

    expect(Blueprint::sole()->data['tabs'])->toBe([
        ['handle' => 'main', 'display' => 'Main', 'sections' => [
            ['handle' => 'main', 'display' => 'Main'],
        ]],
    ]);
});

test('a tab with handle "sidebar" shows a special badge, unlike a regular tab', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->set('tabs.1.handle', 'sidebar')
        ->set('tabs.1.display', 'Sidebar')
        ->call('confirmTab')
        ->call('addTab')
        ->set('tabs.2.handle', 'extra')
        ->set('tabs.2.display', 'Extra')
        ->call('confirmTab');

    $html = $component->html();

    expect($html)->toContain('bg-indigo-300');
    expect($html)->toContain('bg-orange-300');
});

test('removing a tab reassigns its fields back to main', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->set('tabs.1.handle', 'sidebar')
        ->set('tabs.1.display', 'Sidebar')
        ->set('fields.0.handle', 'seo_title')
        ->set('fields.0.tab', 'sidebar')
        ->call('removeTab', 1)
        ->assertCount('tabs', 1)
        ->assertSet('fields.0.tab', 'main');
});

test('a newly added tab opens in edit mode and can be closed once named', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->assertSet('editingTabIndex', null)
        ->call('addTab')
        ->assertSet('editingTabIndex', 1)
        ->set('tabs.1.handle', 'sidebar')
        ->set('tabs.1.display', 'Sidebar')
        ->set('editingTabIndex', null)
        ->assertSet('editingTabIndex', null)
        ->assertSet('tabs.1.handle', 'sidebar');
});

test('canceling a newly added, still-unnamed tab removes it entirely', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->assertCount('tabs', 2)
        ->call('cancelTab', 1)
        ->assertCount('tabs', 1)
        ->assertSet('editingTabIndex', null);
});

test('canceling a newly added tab removes it even if a label/handle was already typed in', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->set('tabs.1.display', 'Sidebar')
        ->set('tabs.1.handle', 'sidebar')
        ->call('cancelTab', 1)
        ->assertCount('tabs', 1)
        ->assertSet('editingTabIndex', null);
});

test('canceling an already-confirmed tab just exits edit mode, without removing it', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->set('tabs.1.handle', 'sidebar')
        ->call('confirmTab')
        ->set('editingTabIndex', 1)
        ->call('cancelTab', 1)
        ->assertCount('tabs', 2)
        ->assertSet('editingTabIndex', null)
        ->assertSet('tabs.1.handle', 'sidebar');
});

test('canceling a newly added, still-unnamed section removes it entirely', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addSection', 0)
        ->assertCount('tabs.0.sections', 2)
        ->call('cancelSection', 0, 1)
        ->assertCount('tabs.0.sections', 1)
        ->assertSet('editingSection', null);
});

test('canceling a newly added section removes it even if a label/handle was already typed in', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addSection', 0)
        ->set('tabs.0.sections.1.display', 'SEO')
        ->set('tabs.0.sections.1.handle', 'seo')
        ->call('cancelSection', 0, 1)
        ->assertCount('tabs.0.sections', 1)
        ->assertSet('editingSection', null);
});

test('canceling an already-confirmed section just exits edit mode, without removing it', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addSection', 0)
        ->set('tabs.0.sections.1.handle', 'seo')
        ->call('confirmSection')
        ->set('editingSection', [0, 1])
        ->call('cancelSection', 0, 1)
        ->assertCount('tabs.0.sections', 2)
        ->assertSet('editingSection', null)
        ->assertSet('tabs.0.sections.1.handle', 'seo');
});

test('removing the tab currently being edited exits edit mode', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addTab')
        ->assertSet('editingTabIndex', 1)
        ->call('removeTab', 1)
        ->assertSet('editingTabIndex', null);
});

test('a section can be added to a tab and a field assigned to it', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('tabs', [
            ['handle' => 'main', 'display' => 'Main', 'sections' => [
                ['handle' => 'seo', 'display' => 'SEO'],
            ]],
        ])
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => '', 'tab' => 'main', 'section' => ''],
            ['handle' => 'seo_title', 'display' => 'Seo Title', 'type' => 'text', 'required' => false, 'options' => '', 'tab' => 'main', 'section' => 'seo'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    expect($blueprint->data['tabs'][0]['sections'])->toBe([
        ['handle' => 'seo', 'display' => 'SEO'],
    ]);
    expect($blueprint->data['fields'][0]['section'])->toBeNull();
    expect($blueprint->data['fields'][1]['section'])->toBe('seo');
});

test('a freshly added, not-yet-named section does not duplicate ungrouped fields in the builder', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(BlueprintForm::class)
        ->set('fields.0.handle', 'headline')
        ->set('fields.0.section', '')
        ->call('addSection', 0);

    $handleOccurrences = substr_count($component->html(), 'wire:model="fields.0.handle"');

    expect($handleOccurrences)->toBe(1);
});

test('a newly added section opens in edit mode', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->assertSet('editingSection', null)
        ->call('addSection', 0)
        ->assertSet('editingSection', [0, 1])
        ->set('tabs.0.sections.1.handle', 'seo')
        ->set('editingSection', null)
        ->assertSet('editingSection', null)
        ->assertSet('tabs.0.sections.1.handle', 'seo');
});

test('removing a section ungroups its fields back to no section', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->call('addSection', 0)
        ->set('tabs.0.sections.1.handle', 'seo')
        ->set('fields.0.handle', 'seo_title')
        ->set('fields.0.section', 'seo')
        ->call('removeSection', 0, 1)
        ->assertCount('tabs.0.sections', 1)
        ->assertSet('fields.0.section', '');
});

test('a field cannot be assigned to a section that does not exist in its tab', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => '', 'tab' => 'main', 'section' => 'does_not_exist'],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.section']);
});

test('a section handle must be unique within its tab', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->set('tabs', [
            ['handle' => 'main', 'display' => 'Main', 'sections' => [
                ['handle' => 'seo', 'display' => 'SEO'],
                ['handle' => 'seo', 'display' => 'Duplicate'],
            ]],
        ])
        ->call('save')
        ->assertHasErrors(['tabs.0.sections.1.handle']);
});

test('a new tab requires a unique, valid handle other than "main"', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => ''],
        ])
        ->set('tabs', [
            ['handle' => 'main', 'display' => 'Main'],
            ['handle' => 'main', 'display' => 'Duplicate'],
        ])
        ->call('save')
        ->assertHasErrors(['tabs.1.handle']);
});

test('a field cannot be assigned to a tab that does not exist', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'headline', 'display' => 'Headline', 'type' => 'text', 'required' => false, 'options' => '', 'tab' => 'does_not_exist'],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.tab']);
});

test('field builder can add, reorder and remove fields', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->assertCount('fields', 1)
        ->call('addField')
        ->assertCount('fields', 2)
        ->set('fields.0.handle', 'first')
        ->set('fields.1.handle', 'second')
        ->set('fields.1.section', 'main')
        ->call('moveFieldDown', [0])
        ->assertSet('fields.0.handle', 'second')
        ->assertSet('fields.1.handle', 'first')
        ->call('removeField', [0])
        ->assertCount('fields', 1)
        ->assertSet('fields.0.handle', 'first');
});

test('field builder can add, reorder and remove fields nested inside a replicator, to any depth', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('fields.0.type', 'replicator')
        ->call('addReplicatorField', [0])
        ->assertCount('fields.0.replicator_fields', 1)
        ->set('fields.0.replicator_fields.0.type', 'replicator')
        ->call('addReplicatorField', [0, 0])
        ->assertCount('fields.0.replicator_fields.0.replicator_fields', 1)
        ->set('fields.0.replicator_fields.0.replicator_fields.0.handle', 'deep_field')
        ->call('addReplicatorField', [0, 0])
        ->set('fields.0.replicator_fields.0.replicator_fields.1.handle', 'deep_field_two')
        ->call('moveFieldDown', [0, 0, 0])
        ->assertSet('fields.0.replicator_fields.0.replicator_fields.0.handle', 'deep_field_two')
        ->assertSet('fields.0.replicator_fields.0.replicator_fields.1.handle', 'deep_field')
        ->call('removeField', [0, 0, 0])
        ->assertCount('fields.0.replicator_fields.0.replicator_fields', 1)
        ->assertSet('fields.0.replicator_fields.0.replicator_fields.0.handle', 'deep_field');
});

test('blueprint can be updated and fields round-trip through edit, including conditions', function () {
    $this->actingAs(User::factory()->create());

    $blueprint = Blueprint::create([
        'namespace' => null,
        'handle' => 'post',
        'data' => ['fields' => [
            ['handle' => 'type', 'display' => 'Type', 'type' => 'select', 'required' => false, 'placeholder' => null, 'default' => null, 'validation' => null, 'instructions' => null, 'validate_sometimes' => false, 'conditions' => null, 'options' => ['article', 'video']],
            ['handle' => 'video_url', 'display' => 'Video Url', 'type' => 'text', 'required' => false, 'placeholder' => null, 'default' => null, 'validation' => 'url', 'instructions' => null, 'validate_sometimes' => true, 'conditions' => ['mode' => 'show', 'field' => 'type', 'operator' => 'equals', 'value' => 'video']],
        ]],
    ]);

    Livewire::test(BlueprintForm::class, ['blueprint' => $blueprint])
        ->assertSet('fields.1.sometimes', true)
        ->assertSet('fields.1.condition_mode', 'show')
        ->assertSet('fields.1.condition_field', 'type')
        ->assertSet('fields.1.condition_operator', 'equals')
        ->assertSet('fields.1.condition_value', 'video')
        ->set('handle', 'article')
        ->call('save')
        ->assertHasNoErrors();

    $blueprint->refresh();

    expect($blueprint->handle)->toBe('article');
    expect($blueprint->data['fields'][1]['conditions'])->toBe([
        'mode' => 'show', 'field' => 'type', 'operator' => 'equals', 'value' => 'video',
    ]);
    expect($blueprint->data['fields'][1]['validate_sometimes'])->toBeTrue();
});

test('saving an already-existing blueprint stays on the form instead of redirecting to the listing', function () {
    $this->actingAs(User::factory()->create());

    $blueprint = Blueprint::create(['namespace' => null, 'handle' => 'post', 'data' => ['fields' => []]]);

    Livewire::test(BlueprintForm::class, ['blueprint' => $blueprint])
        ->set('fields.0.handle', 'headline')
        ->set('fields.0.display', 'Headline')
        ->call('save')
        ->assertHasNoErrors()
        ->assertNoRedirect();
});

test('saving a brand-new blueprint redirects to its own edit page, not the listing', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields.0.handle', 'headline')
        ->set('fields.0.display', 'Headline')
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    $component->assertRedirect(route('blueprints.edit', $blueprint));
});

test('the fields-collapsed-by-default preference is saved and reloaded on edit', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields.0.handle', 'headline')
        ->set('fields.0.display', 'Headline')
        ->set('fieldsCollapsedByDefault', true)
        ->call('save')
        ->assertHasNoErrors();

    $blueprint = Blueprint::sole();

    expect($blueprint->data['fields_collapsed_by_default'])->toBeTrue();

    Livewire::test(BlueprintForm::class, ['blueprint' => $blueprint])
        ->assertSet('fieldsCollapsedByDefault', true);
});

test('blueprint can be deleted', function () {
    $this->actingAs(User::factory()->create());

    $blueprint = Blueprint::create(['namespace' => null, 'handle' => 'post', 'data' => ['fields' => []]]);

    Livewire::test(BlueprintManager::class)->call('delete', $blueprint->id);

    expect(Blueprint::find($blueprint->id))->toBeNull();
});

test('fieldset can be deleted', function () {
    $this->actingAs(User::factory()->create());

    $fieldset = Fieldset::create(['handle' => 'seo', 'data' => ['fields' => []]]);

    Livewire::test(FieldsetManager::class)->call('delete', $fieldset->id);

    expect(Fieldset::find($fieldset->id))->toBeNull();
});

test('fieldsets page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin-panel/fieldsets')->assertOk();
});

test('fieldset can be created with a field builder like blueprints', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FieldsetForm::class)
        ->set('handle', 'seo')
        ->set('fields.0.handle', 'meta_title')
        ->set('fields.0.display', 'Meta Title')
        ->call('save')
        ->assertHasNoErrors();

    $fieldset = Fieldset::sole();

    expect($fieldset->handle)->toBe('seo');
    expect($fieldset->data['fields'])->toHaveCount(1);
    expect($fieldset->data['fields'][0]['handle'])->toBe('meta_title');
    expect($fieldset->data['fields'][0])->not->toHaveKey('tab');
    expect($fieldset->data['fields'][0])->not->toHaveKey('section');
});

test('fieldset handle must be unique', function () {
    $this->actingAs(User::factory()->create());

    Fieldset::create(['handle' => 'seo', 'data' => ['fields' => []]]);

    Livewire::test(FieldsetForm::class)
        ->set('handle', 'seo')
        ->set('fields.0.handle', 'meta_title')
        ->call('save')
        ->assertHasErrors(['handle']);
});

test('a fieldset requires at least one field', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FieldsetForm::class)
        ->set('handle', 'seo')
        ->set('fields', [])
        ->call('save')
        ->assertHasErrors(['fields']);
});

test('fieldset fields can be reordered by drag and drop', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(FieldsetForm::class)
        ->set('fields.0.handle', 'field_one')
        ->call('addField')
        ->set('fields.1.handle', 'field_two');

    $component
        ->call('reorderFields', $component->get('fields.0._uid'), 1, json_encode(['tab' => 'main', 'section' => '']))
        ->assertSet('fields.0.handle', 'field_two')
        ->assertSet('fields.1.handle', 'field_one');
});

test('fieldset can be edited', function () {
    $this->actingAs(User::factory()->create());

    $fieldset = Fieldset::create(['handle' => 'seo', 'data' => ['fields' => [
        ['handle' => 'meta_title', 'display' => 'Meta Title', 'type' => 'text', 'required' => false],
    ]]]);

    Livewire::test(FieldsetForm::class, ['fieldset' => $fieldset])
        ->assertSet('fields.0.handle', 'meta_title')
        ->set('fields.0.required', true)
        ->call('save')
        ->assertHasNoErrors();

    expect($fieldset->refresh()->data['fields'][0]['required'])->toBeTrue();
});

test('saving an already-existing fieldset stays on the form instead of redirecting to the listing', function () {
    $this->actingAs(User::factory()->create());

    $fieldset = Fieldset::create(['handle' => 'seo', 'data' => ['fields' => [
        ['handle' => 'meta_title', 'display' => 'Meta Title', 'type' => 'text', 'required' => false],
    ]]]);

    Livewire::test(FieldsetForm::class, ['fieldset' => $fieldset])
        ->call('save')
        ->assertHasNoErrors()
        ->assertNoRedirect();
});

test('saving a brand-new fieldset redirects to its own edit page, not the listing', function () {
    $this->actingAs(User::factory()->create());

    $component = Livewire::test(FieldsetForm::class)
        ->set('handle', 'seo')
        ->set('fields.0.handle', 'meta_title')
        ->set('fields.0.display', 'Meta Title')
        ->call('save')
        ->assertHasNoErrors();

    $fieldset = Fieldset::sole();

    $component->assertRedirect(route('blueprints.fieldsets.edit', $fieldset));
});

test('assets field can be created scoped to an asset container', function () {
    $this->actingAs(User::factory()->create());

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'gallery', 'display' => 'Gallery', 'type' => 'assets', 'required' => false, 'options' => 'photos'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['type'])->toBe('assets');
    expect($field['options'])->toBe('photos');
});

test('taxonomy field can be created scoped to a taxonomy with min/max selections', function () {
    $this->actingAs(User::factory()->create());

    Taxonomy::create(['handle' => 'categories', 'title' => 'Categories', 'sites' => []]);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'categories', 'display' => 'Categories', 'type' => 'taxonomy', 'required' => false, 'options' => 'categories', 'min_items' => '1', 'max_items' => '3'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['type'])->toBe('taxonomy');
    expect($field['options'])->toBe('categories');
    expect($field['min_items'])->toBe(1);
    expect($field['max_items'])->toBe(3);
});

test('an author field can be created with no extra configuration', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'author', 'display' => 'Author', 'type' => 'author', 'required' => false],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['type'])->toBe('author');
    expect($field['placeholder'])->toBeNull();
    expect($field['default'])->toBeNull();
});

test('taxonomy field requires a taxonomy to be selected', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'categories', 'display' => 'Categories', 'type' => 'taxonomy', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.options']);
});

test('a blueprint field can import an existing fieldset', function () {
    $this->actingAs(User::factory()->create());

    Fieldset::create(['handle' => 'seo', 'data' => ['fields' => [
        ['handle' => 'meta_title', 'display' => 'Meta Title', 'type' => 'text', 'required' => false],
    ]]]);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'seo', 'display' => '', 'type' => 'fieldset', 'required' => false, 'options' => 'seo'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['type'])->toBe('fieldset');
    expect($field['options'])->toBe('seo');
});

test('a fieldset field requires an existing fieldset to be selected', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'seo', 'display' => '', 'type' => 'fieldset', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.options']);
});

test('assets field requires an asset container to be selected', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'gallery', 'display' => 'Gallery', 'type' => 'assets', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.options']);
});

test('assets field container must reference an existing asset container', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'gallery', 'display' => 'Gallery', 'type' => 'assets', 'required' => false, 'options' => 'does-not-exist'],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.options']);
});

test('assets field can be given a min and max item count', function () {
    $this->actingAs(User::factory()->create());

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'gallery', 'display' => 'Gallery', 'type' => 'assets', 'required' => false, 'options' => 'photos', 'min_items' => '1', 'max_items' => '3'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['min_items'])->toBe(1);
    expect($field['max_items'])->toBe(3);
});

test('assets field max items cannot be less than min items', function () {
    $this->actingAs(User::factory()->create());

    AssetContainer::create(['handle' => 'photos', 'title' => 'Photos', 'disk' => 'public']);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'gallery', 'display' => 'Gallery', 'type' => 'assets', 'required' => false, 'options' => 'photos', 'min_items' => '5', 'max_items' => '2'],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.max_items']);
});

test('radio field can be created with options and a default value', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'size', 'display' => 'Size', 'type' => 'radio', 'required' => false, 'options' => 'small, medium, large', 'default' => 'medium'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['type'])->toBe('radio');
    expect($field['options'])->toBe(['small', 'medium', 'large']);
    expect($field['default'])->toBe('medium');
});

test('checkbox field stores its options and preset default values as arrays', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'toppings', 'display' => 'Toppings', 'type' => 'checkbox', 'required' => false, 'options' => 'cheese, olives, mushrooms', 'default' => 'cheese, olives'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['type'])->toBe('checkbox');
    expect($field['options'])->toBe(['cheese', 'olives', 'mushrooms']);
    expect($field['default'])->toBe(['cheese', 'olives']);
});

test('checkbox field can be given a min and max selection count', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'toppings', 'display' => 'Toppings', 'type' => 'checkbox', 'required' => false, 'options' => 'cheese, olives, mushrooms', 'min_items' => '1', 'max_items' => '2'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['min_items'])->toBe(1);
    expect($field['max_items'])->toBe(2);
});

test('checkbox field max selections cannot be less than min selections', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'toppings', 'display' => 'Toppings', 'type' => 'checkbox', 'required' => false, 'options' => 'cheese, olives', 'min_items' => '3', 'max_items' => '1'],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.max_items']);
});

test('link field can be scoped to allowed collections', function () {
    $this->actingAs(User::factory()->create());

    Collection::create(['title' => 'Pages', 'handle' => 'pages', 'settings' => []]);
    Collection::create(['title' => 'Blog', 'handle' => 'blog', 'settings' => []]);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'required' => false, 'options' => 'pages, blog'],
        ])
        ->call('save')
        ->assertHasNoErrors();

    $field = Blueprint::sole()->data['fields'][0];

    expect($field['type'])->toBe('link');
    expect($field['options'])->toBe(['pages', 'blog']);
    expect($field['default'])->toBeNull();
});

test('link field can be left unrestricted (any collection allowed)', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'required' => false, 'options' => ''],
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect(Blueprint::sole()->data['fields'][0]['options'])->toBe([]);
});

test('link field rejects an allowed-collection handle that does not exist', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields', [
            ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'required' => false, 'options' => 'does-not-exist'],
        ])
        ->call('save')
        ->assertHasErrors(['fields.0.options']);
});

test('opening the blueprint create form requires the configure fields permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(BlueprintForm::class)->assertForbidden();
});

test('saving a blueprint requires the configure fields permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure fields']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(BlueprintForm::class)
        ->set('handle', 'post')
        ->set('fields.0.handle', 'headline')
        ->set('fields.0.display', 'Headline')
        ->call('save')
        ->assertHasNoErrors();

    expect(Blueprint::sole()->handle)->toBe('post');
});

test('deleting a blueprint requires the configure fields permission', function () {
    $blueprint = Blueprint::create(['namespace' => null, 'handle' => 'post', 'data' => ['fields' => []]]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(BlueprintManager::class)->call('delete', $blueprint->id)->assertForbidden();
});

test('a user with configure fields permission can delete a blueprint', function () {
    $blueprint = Blueprint::create(['namespace' => null, 'handle' => 'post', 'data' => ['fields' => []]]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure fields']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(BlueprintManager::class)->call('delete', $blueprint->id);

    expect(Blueprint::find($blueprint->id))->toBeNull();
});

test('opening the fieldset create form requires the configure fields permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(FieldsetForm::class)->assertForbidden();
});

test('saving a fieldset requires the configure fields permission', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure fields']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(FieldsetForm::class)
        ->set('handle', 'seo')
        ->set('fields.0.handle', 'meta_title')
        ->set('fields.0.display', 'Meta Title')
        ->call('save')
        ->assertHasNoErrors();

    expect(Fieldset::sole()->handle)->toBe('seo');
});

test('deleting a fieldset requires the configure fields permission', function () {
    $fieldset = Fieldset::create(['handle' => 'seo', 'data' => ['fields' => []]]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(FieldsetManager::class)->call('delete', $fieldset->id)->assertForbidden();
});

test('a user with configure fields permission can delete a fieldset', function () {
    $fieldset = Fieldset::create(['handle' => 'seo', 'data' => ['fields' => []]]);

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure fields']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(FieldsetManager::class)->call('delete', $fieldset->id);

    expect(Fieldset::find($fieldset->id))->toBeNull();
});

test('viewing the blueprints page requires the "configure fields" permission, not just "access cp"', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    $this->get('/admin-panel/blueprints')->assertForbidden();
});

test('a user with "configure fields" permission can view the blueprints page', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'configure fields']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    $this->get('/admin-panel/blueprints')->assertOk();
});

test('viewing the fieldsets page requires the "configure fields" permission, not just "access cp"', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    $this->get('/admin-panel/fieldsets')->assertForbidden();
});
