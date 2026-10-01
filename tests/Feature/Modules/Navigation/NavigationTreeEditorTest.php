<?php

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Navigation\Livewire\NavigationTreeEditor;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Navigation\Services\NavigationTreeService;
use App\Modules\Sites\Models\Site;
use App\Modules\Users\Models\Role;
use Livewire\Livewire;

function makeNavigation(): Navigation
{
    return Navigation::create(['title' => 'Main menu', 'handle' => 'main']);
}

function makeNavigationSite(string $handle, int $order = 0): Site
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

test('tree editor page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();

    $this->get("/admin-panel/navigations/{$navigation->handle}")->assertOk();
});

test('adding a root item auto-expands it, and nothing persists until save', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();

    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('addRoot');

    $rootId = $component->get('tree')[0]['id'];

    $component->assertSet('expanded', [$rootId])
        ->set('tree.0.title', 'Home')
        ->set('tree.0.url', '/');

    expect(app(NavigationTreeService::class)->load($navigation->fresh()))->toBe([]);

    $component->call('save');

    $tree = app(NavigationTreeService::class)->load($navigation->fresh());

    expect($tree)->toHaveCount(1);
    expect($tree[0]['title'])->toBe('Home');
    expect($tree[0]['url'])->toBe('/');
});

test('adding a child auto-expands it and save persists the whole tree', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    app(NavigationTreeService::class)->addItem($navigation, null, ['type' => 'internal', 'title' => 'Root', 'url' => '/']);
    $rootId = app(NavigationTreeService::class)->load($navigation)[0]['id'];

    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('addChild', $rootId);

    $childId = $component->get('tree')[0]['children'][0]['id'];

    $component->assertSet('expanded', [$rootId, $childId])
        ->set('tree.0.children.0.title', 'Sub page')
        ->set('tree.0.children.0.url', '/sub')
        ->call('save');

    $tree = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($tree[0]['children'])->toHaveCount(1);
    expect($tree[0]['children'][0]['title'])->toBe('Sub page');
});

test('switching an item to page type clears its url, and back clears page_id', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    app(NavigationTreeService::class)->addItem($navigation, null, ['type' => 'internal', 'title' => 'Item', 'url' => '/foo']);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->set('tree.0.type', 'page')
        ->set('tree.0.page_id', 'entry-id')
        ->assertSet('tree.0.url', null)
        ->set('tree.0.type', 'internal')
        ->assertSet('tree.0.page_id', null)
        ->call('save');

    $tree = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($tree[0]['type'])->toBe('internal');
    expect($tree[0]['page_id'])->toBeNull();
});

test('root items can be deleted with their children once saved', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);
    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Home', 'url' => '/']);
    $rootId = $service->load($navigation)[0]['id'];
    $service->addItem($navigation, $rootId, ['type' => 'internal', 'title' => 'Sub page', 'url' => '/sub']);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('askDelete', $rootId)
        ->assertSet('deletingId', $rootId)
        ->call('confirmDelete')
        ->call('save');

    expect($service->load($navigation->fresh()))->toBe([]);
});

test('toggle tracks expanded state locally without touching the stored tree', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    app(NavigationTreeService::class)->addItem($navigation, null, ['type' => 'internal', 'title' => 'Home', 'url' => '/']);
    $rootId = app(NavigationTreeService::class)->load($navigation)[0]['id'];

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertSet('expanded', [])
        ->call('toggle', $rootId)
        ->assertSet('expanded', [$rootId])
        ->call('toggle', $rootId)
        ->assertSet('expanded', []);
});

test('adding a child under a level-2 item is rejected', function () {
    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);

    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Root', 'url' => '/']);
    $tree = $service->load($navigation);
    $rootId = $tree[0]['id'];

    $service->addItem($navigation, $rootId, ['type' => 'internal', 'title' => 'Child', 'url' => '/child']);
    $tree = $service->load($navigation);
    $childId = $tree[0]['children'][0]['id'];

    expect(fn () => $service->addItem($navigation, $childId, ['type' => 'internal', 'title' => 'Grandchild', 'url' => '/g']))
        ->toThrow(InvalidArgumentException::class);
});

test('moving a root item with children under another item is rejected', function () {
    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);

    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Parent', 'url' => '/parent']);
    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Other root', 'url' => '/other']);

    $tree = $service->load($navigation);
    $parentId = $tree[0]['id'];
    $otherId = $tree[1]['id'];

    $service->addItem($navigation, $parentId, ['type' => 'internal', 'title' => 'Child', 'url' => '/child']);

    expect(fn () => $service->moveItem($navigation, $parentId, $otherId, 0))
        ->toThrow(InvalidArgumentException::class);
});

test('items can be reordered by drag-and-drop, persisted on save', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);

    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'First', 'url' => '/1']);
    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Second', 'url' => '/2']);

    $tree = $service->load($navigation);
    [$firstId, $secondId] = [$tree[0]['id'], $tree[1]['id']];

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('sortRoot', $firstId, 1)
        ->assertSet('tree.0.id', $secondId)
        ->assertSet('tree.1.id', $firstId)
        ->call('save');

    $tree = $service->load($navigation->fresh());
    expect($tree[0]['id'])->toBe($secondId);
    expect($tree[1]['id'])->toBe($firstId);
});

test('a child can be moved to become a child of a different parent via the move menu', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);

    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Parent A', 'url' => '/a']);
    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Parent B', 'url' => '/b']);
    $tree = $service->load($navigation);
    [$parentAId, $parentBId] = [$tree[0]['id'], $tree[1]['id']];

    $service->addItem($navigation, $parentAId, ['type' => 'internal', 'title' => 'Child', 'url' => '/a/child']);
    $childId = $service->load($navigation)[0]['children'][0]['id'];

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('askMove', $childId)
        ->assertSet('movingId', $childId)
        ->set('moveTargetParentId', $parentBId)
        ->call('confirmMove')
        ->call('save');

    $tree = $service->load($navigation->fresh());
    expect($tree[0]['children'])->toBe([]);
    expect($tree[1]['children'])->toHaveCount(1);
    expect($tree[1]['children'][0]['id'])->toBe($childId);
});

test('a child can be promoted to level 1 via the move menu', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);

    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Parent', 'url' => '/parent']);
    $parentId = $service->load($navigation)[0]['id'];
    $service->addItem($navigation, $parentId, ['type' => 'internal', 'title' => 'Child', 'url' => '/child']);
    $childId = $service->load($navigation)[0]['children'][0]['id'];

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('askMove', $childId)
        ->call('confirmMove')
        ->call('save');

    $tree = $service->load($navigation->fresh());
    expect($tree)->toHaveCount(2);
    expect($tree[0]['children'])->toBe([]);
    expect($tree[1]['id'])->toBe($childId);
});

test('moving a parent with children under another parent requires resolving its children first', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);

    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Parent A', 'url' => '/a']);
    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Parent B', 'url' => '/b']);
    $tree = $service->load($navigation);
    [$parentAId, $parentBId] = [$tree[0]['id'], $tree[1]['id']];
    $service->addItem($navigation, $parentAId, ['type' => 'internal', 'title' => 'Child', 'url' => '/a/child']);

    // Without choosing a children strategy, the move is rejected and nothing changes.
    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('askMove', $parentAId)
        ->set('moveTargetParentId', $parentBId)
        ->call('confirmMove');

    expect($component->get('tree')[0]['id'])->toBe($parentAId);
    expect($component->get('tree')[0]['children'])->toHaveCount(1);

    // Choosing "promote" moves the parent under Parent B and lifts its child to level 1.
    $component->set('moveChildrenStrategy', 'promote')
        ->call('confirmMove')
        ->call('save');

    $tree = $service->load($navigation->fresh());
    expect($tree)->toHaveCount(2);
    expect(collect($tree)->pluck('title')->all())->toContain('Child');
    $parentB = collect($tree)->firstWhere('id', $parentBId);
    expect($parentB['children'][0]['id'])->toBe($parentAId);
    expect($parentB['children'][0]['children'])->toBe([]);
});

test('moving a parent with children under another parent can delete the children instead', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);

    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Parent A', 'url' => '/a']);
    $service->addItem($navigation, null, ['type' => 'internal', 'title' => 'Parent B', 'url' => '/b']);
    $tree = $service->load($navigation);
    [$parentAId, $parentBId] = [$tree[0]['id'], $tree[1]['id']];
    $service->addItem($navigation, $parentAId, ['type' => 'internal', 'title' => 'Child', 'url' => '/a/child']);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('askMove', $parentAId)
        ->set('moveTargetParentId', $parentBId)
        ->set('moveChildrenStrategy', 'delete')
        ->call('confirmMove')
        ->call('save');

    $tree = $service->load($navigation->fresh());
    expect($tree)->toHaveCount(1);
    expect($tree[0]['id'])->toBe($parentBId);
    expect($tree[0]['children'][0]['id'])->toBe($parentAId);
    expect($tree[0]['children'][0]['children'])->toBe([]);
});

test('opening the item menu and duplicating copies the item as the next sibling', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    app(NavigationTreeService::class)->addItem($navigation, null, ['type' => 'internal', 'title' => 'Home', 'url' => '/']);
    $rootId = app(NavigationTreeService::class)->load($navigation)[0]['id'];

    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('duplicateItem', $rootId);

    expect($component->get('tree'))->toHaveCount(2);
    expect($component->get('tree')[1]['title'])->toBe('Home');
    expect($component->get('tree')[1]['id'])->not->toBe($rootId);

    $component->call('save');

    $tree = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($tree)->toHaveCount(2);
});

test('deleting an item requires confirming the delete modal', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    app(NavigationTreeService::class)->addItem($navigation, null, ['type' => 'internal', 'title' => 'Home', 'url' => '/']);
    $rootId = app(NavigationTreeService::class)->load($navigation)[0]['id'];

    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('askDelete', $rootId);

    expect($component->get('tree'))->toHaveCount(1);

    $component->call('confirmDelete')
        ->assertSet('tree', [])
        ->assertSet('deletingId', null)
        ->call('save');

    expect(app(NavigationTreeService::class)->load($navigation->fresh()))->toBe([]);
});

test('a navigation with no blueprint fields uses the legacy tree editor, with an Add item button', function () {
    $this->actingAs(User::factory()->create());

    config(['cms.protected_navigations' => ['sidebar-admin']]);
    $navigation = Navigation::create(['title' => 'Sidebar Admin', 'handle' => 'sidebar-admin']);

    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertSeeHtml('Add item');

    $component->call('addRoot');
    expect($component->get('tree')[0])->toHaveKeys(['type', 'title', 'url', 'target']);

    $component->set('tree.0.title', 'Dashboard')
        ->set('tree.0.url', '/dashboard')
        ->call('save');

    $tree = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($tree[0]['title'])->toBe('Dashboard');
});

test('a navigation with blueprint fields uses a single blueprint-driven form, with no Add item button', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'header_text', 'display' => 'Header Text', 'type' => 'text', 'required' => false, 'placeholder' => null, 'instructions' => null],
        ]],
    ]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertDontSeeHtml('Add item')
        ->set('data.header_text', 'Welcome')
        ->call('save')
        ->assertHasNoErrors();

    expect(app(NavigationTreeService::class)->load($navigation->fresh()))->toBe(['header_text' => 'Welcome']);
});

test('a navigation field with a "show when" condition only renders once it matches', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'show_header', 'display' => 'Show Header', 'type' => 'toggle', 'required' => false, 'placeholder' => null, 'default' => false, 'instructions' => null, 'conditions' => null],
            ['handle' => 'header_text', 'display' => 'Header Text', 'type' => 'text', 'required' => false, 'placeholder' => null, 'instructions' => null, 'conditions' => ['mode' => 'show', 'field' => 'show_header', 'operator' => 'equals', 'value' => 'true']],
        ]],
    ]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertDontSee('Header Text')
        ->set('data.show_header', true)
        ->assertSee('Header Text');
});

test('a navigation field referenced by another field\'s condition is bound live', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'show_header', 'display' => 'Show Header', 'type' => 'toggle', 'required' => false, 'placeholder' => null, 'default' => false, 'instructions' => null, 'conditions' => null],
            ['handle' => 'header_text', 'display' => 'Header Text', 'type' => 'text', 'required' => false, 'placeholder' => null, 'instructions' => null, 'conditions' => ['mode' => 'show', 'field' => 'show_header', 'operator' => 'equals', 'value' => 'true']],
        ]],
    ]);

    $html = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])->html();

    expect($html)->toContain('wire:model.live="data.show_header"');
});

test('saving fails validation when a required blueprint field is left blank', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'header_text', 'display' => 'Header Text', 'type' => 'text', 'required' => true, 'placeholder' => null, 'instructions' => null],
        ]],
    ]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('save')
        ->assertHasErrors(['data.header_text' => 'required']);
});

test('the default language is whichever site has the topmost order', function () {
    $this->actingAs(User::factory()->create());

    makeNavigationSite('english', 1);
    makeNavigationSite('indonesian', 0);

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'header_text', 'display' => 'Header Text', 'type' => 'text', 'required' => false, 'placeholder' => null, 'instructions' => null],
        ]],
    ]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertSet('activeSite', 'indonesian');
});

test('a non-default language inherits the default language live until edited directly, then stays independent', function () {
    $this->actingAs(User::factory()->create());

    makeNavigationSite('indonesian', 0);
    makeNavigationSite('english', 1);

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'header_text', 'display' => 'Header Text', 'type' => 'text', 'required' => false, 'placeholder' => null, 'instructions' => null],
        ]],
    ]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertSet('activeSite', 'indonesian')
        ->set('data.header_text', 'Selamat datang')
        ->call('save')
        ->assertHasNoErrors()
        // English hasn't been touched yet, so switching to it shows the default language's value directly.
        ->set('activeSite', 'english')
        ->assertSet('data.header_text', 'Selamat datang')
        ->call('save')
        ->assertHasNoErrors();

    $service = app(NavigationTreeService::class);
    // Saving english untouched stores no override, so it keeps live-following the default language.
    expect($service->load($navigation->fresh(), 'english'))->toBe([]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->set('data.header_text', 'Selamat datang lagi')
        ->call('save');

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation, 'activeSite' => 'english'])
        ->assertSet('data.header_text', 'Selamat datang lagi')
        // Editing english directly now makes it independent of the default language.
        ->set('data.header_text', 'Welcome')
        ->call('save')
        ->assertHasNoErrors();

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->set('data.header_text', 'Selamat datang lagi diubah')
        ->call('save');

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation, 'activeSite' => 'english'])
        ->assertSet('data.header_text', 'Welcome');
});

test('menu links are a replicator field the admin can add rows to directly, without a custom tree', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            [
                'handle' => 'header_text', 'display' => 'Header Text', 'type' => 'text',
                'required' => false, 'placeholder' => null, 'instructions' => null,
            ],
            [
                'handle' => 'links', 'display' => 'Links', 'type' => 'replicator',
                'required' => false, 'placeholder' => null, 'instructions' => null,
                'replicator_fields' => [
                    ['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => true, 'placeholder' => null, 'instructions' => null],
                    ['handle' => 'link', 'display' => 'Link', 'type' => 'link', 'required' => false, 'placeholder' => null, 'instructions' => null, 'options' => []],
                ],
            ],
        ]],
    ]);

    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertSet('data.links', [])
        ->call('addReplicatorRow', ['links'])
        ->call('addReplicatorRow', ['links']);

    expect($component->get('data.links'))->toHaveCount(2);

    $component->set('data.links.0.title', 'Home')
        ->set('data.links.0.link', '/')
        ->set('data.links.1.title', 'About')
        ->set('data.links.1.link', '/about')
        ->call('save')
        ->assertHasNoErrors();

    $data = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($data['links'])->toHaveCount(2);
    expect($data['links'][0]['title'])->toBe('Home');
    expect($data['links'][1]['link'])->toBe('/about');

    $component->call('removeReplicatorRow', ['links'], 0)
        ->call('save');

    $data = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($data['links'])->toHaveCount(1);
    expect($data['links'][0]['title'])->toBe('About');
});

test('link fields on a navigation only offer entries of the active language', function () {
    $this->actingAs(User::factory()->create());
    makeNavigationSite('indonesian', 0);
    makeNavigationSite('english', 1);
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => []]);

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'required' => false, 'placeholder' => null, 'instructions' => null, 'options' => []],
        ]],
    ]);

    $indonesian = Entry::create(['site' => 'indonesian', 'collection' => 'pages', 'data' => ['title' => 'Beranda']]);
    $english = Entry::create(['site' => 'english', 'collection' => 'pages', 'data' => ['title' => 'Home']]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation, 'activeSite' => 'indonesian'])
        ->call('linkableEntries', [])
        ->assertReturned(fn ($entries) => array_column($entries, 'id') === [$indonesian->id]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation, 'activeSite' => 'english'])
        ->call('linkableEntries', [])
        ->assertReturned(fn ($entries) => array_column($entries, 'id') === [$english->id]);
});

test('a link to the default language\'s entry shows its translated sibling in another language, and saving it untouched keeps following the default', function () {
    $this->actingAs(User::factory()->create());
    makeNavigationSite('indonesian', 0);
    makeNavigationSite('english', 1);
    Collection::create(['handle' => 'pages', 'title' => 'Pages', 'settings' => []]);

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'cta', 'display' => 'Cta', 'type' => 'link', 'required' => false, 'placeholder' => null, 'instructions' => null, 'options' => []],
        ]],
    ]);

    $indonesian = Entry::create(['site' => 'indonesian', 'collection' => 'pages', 'data' => ['title' => 'Berita ID']]);
    $english = Entry::create(['site' => 'english', 'collection' => 'pages', 'origin_id' => $indonesian->id, 'data' => ['title' => 'Berita EN']]);

    // Default language links to its own entry.
    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->set('data.cta', "entry::{$indonesian->id}")
        ->call('save')
        ->assertHasNoErrors();

    // English shows the same content as its translated entry, not the raw
    // default-language reference.
    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation, 'activeSite' => 'english'])
        ->assertSet('data.cta', "entry::{$english->id}")
        ->call('save')
        ->assertHasNoErrors();

    // Leaving it untouched stores no override, so it keeps live-following
    // the default language.
    $service = app(NavigationTreeService::class);
    expect($service->load($navigation->fresh(), 'english'))->toBe([]);

    // Linking to a genuinely different english entry is a real override.
    $otherEnglish = Entry::create(['site' => 'english', 'collection' => 'pages', 'data' => ['title' => 'Other']]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation, 'activeSite' => 'english'])
        ->set('data.cta', "entry::{$otherEnglish->id}")
        ->call('save')
        ->assertHasNoErrors();

    expect($service->load($navigation->fresh(), 'english'))->toBe(['cta' => "entry::{$otherEnglish->id}"]);
});

test('replicator rows can be duplicated and drag-reordered, and never leak their internal _uid into storage', function () {
    $this->actingAs(User::factory()->create());

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            [
                'handle' => 'links', 'display' => 'Links', 'type' => 'replicator',
                'required' => false, 'placeholder' => null, 'instructions' => null,
                'replicator_fields' => [
                    ['handle' => 'title', 'display' => 'Title', 'type' => 'text', 'required' => false, 'placeholder' => null, 'instructions' => null],
                ],
            ],
        ]],
    ]);

    $component = Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('addReplicatorRow', ['links'])
        ->call('addReplicatorRow', ['links'])
        ->set('data.links.0.title', 'Home')
        ->set('data.links.1.title', 'About');

    $rows = $component->get('data.links');
    expect($rows[0]['_uid'])->not->toBe($rows[1]['_uid']);

    $component->call('duplicateReplicatorRow', ['links'], 0);
    $rows = $component->get('data.links');
    expect($rows)->toHaveCount(3);
    expect($rows[1]['title'])->toBe('Home');
    expect($rows[1]['_uid'])->not->toBe($rows[0]['_uid']);

    $homeUid = $rows[0]['_uid'];
    $component->call('reorderReplicatorRow', $homeUid, 2, json_encode(['path' => ['links']]));

    $rows = $component->get('data.links');
    expect($rows[2]['title'])->toBe('Home');

    $component->call('save')->assertHasNoErrors();

    $data = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($data['links'])->toHaveCount(3);
    expect($data['links'][0])->not->toHaveKey('_uid');
});

test('a taxonomy field in a navigation blueprint renders its terms and saves selections', function () {
    $this->actingAs(User::factory()->create());

    Taxonomy::create(['handle' => 'categories', 'title' => 'Categories', 'sites' => []]);
    $news = TaxonomyTerm::create(['site' => 'indonesia', 'slug' => 'news', 'uri' => '/categories/news', 'taxonomy' => 'categories', 'data' => ['title' => 'News']]);
    $sports = TaxonomyTerm::create(['site' => 'indonesia', 'slug' => 'sports', 'uri' => '/categories/sports', 'taxonomy' => 'categories', 'data' => ['title' => 'Sports']]);

    $navigation = makeNavigation();
    Blueprint::create([
        'namespace' => 'navigations.main',
        'handle' => 'main',
        'data' => ['tabs' => [], 'fields' => [
            ['handle' => 'featured_categories', 'display' => 'Featured Categories', 'type' => 'taxonomy', 'options' => 'categories', 'required' => false, 'placeholder' => null, 'instructions' => null],
        ]],
    ]);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->assertSee('News')
        ->assertSee('Sports')
        ->set('data.featured_categories', [$news->id, $sports->id])
        ->call('save')
        ->assertHasNoErrors();

    expect(app(NavigationTreeService::class)->load($navigation->fresh()))
        ->toBe(['featured_categories' => [$news->id, $sports->id]]);
});

test('page-type items resolve their URL dynamically from the entry slug', function () {
    $collection = Collection::create(['handle' => 'pages', 'title' => 'Pages']);
    $entry = Entry::create([
        'site' => 'indonesia', 'published' => true, 'status' => 'published',
        'slug' => 'about-us', 'collection' => $collection->handle, 'data' => ['title' => 'About us'],
    ]);

    $navigation = makeNavigation();
    $service = app(NavigationTreeService::class);

    $service->addItem($navigation, null, ['type' => 'page', 'title' => 'About us', 'page_id' => $entry->id]);
    $tree = $service->load($navigation);

    expect($service->resolveUrl($tree[0]))->toBe('/about-us');

    $entry->update(['slug' => 'about']);

    expect($service->resolveUrl($tree[0]))->toBe('/about');
});

test('viewing the tree editor requires the view navigation permission', function () {
    $navigation = makeNavigation();

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])->assertStatus(403);
});

test('a user with view navigation permission can see the tree editor', function () {
    $navigation = makeNavigation();

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'view main navigation']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])->assertOk();
});

test('adding a root item requires the edit navigation permission', function () {
    $navigation = makeNavigation();

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view main navigation']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])->call('addRoot')->assertStatus(403);
});

test('saving the tree requires the edit navigation permission', function () {
    $navigation = makeNavigation();

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp', 'view main navigation']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])->call('save')->assertStatus(403);
});

test('a user with edit navigation permission can add items and save the tree', function () {
    $navigation = makeNavigation();

    $user = User::factory()->create();
    $role = Role::create(['handle' => 'editor', 'title' => 'Editor', 'permissions' => ['access cp', 'view main navigation', 'edit main navigation']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    Livewire::test(NavigationTreeEditor::class, ['navigation' => $navigation])
        ->call('addRoot')
        ->set('tree.0.title', 'Home')
        ->set('tree.0.url', '/')
        ->call('save');

    $tree = app(NavigationTreeService::class)->load($navigation->fresh());
    expect($tree)->toHaveCount(1);
    expect($tree[0]['title'])->toBe('Home');
});
