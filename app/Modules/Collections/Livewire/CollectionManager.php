<?php

namespace App\Modules\Collections\Livewire;

use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Sites\Models\Site;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Collections')]
class CollectionManager extends Component
{
    public ?int $editingId = null;

    public string $handle = '';

    public string $title = '';

    /**
     * 'single' uses the same URI template on every site; 'per_site' lets
     * each checked site have its own.
     */
    public string $routeMode = 'single';

    /**
     * URI template used for every site when routeMode is 'single'.
     */
    public string $route = '';

    /**
     * URI template per site, e.g. ['indonesia' => 'blog/{slug}', 'english' => 'en/blog/{slug}'].
     * Only used when routeMode is 'per_site'; only keys for currently
     * checked sites are kept.
     *
     * @var array<string, string>
     */
    public array $routes = [];

    /**
     * Which sites this collection is available on. Empty means every site.
     * The default (origin) site is always included and can't be unchecked.
     *
     * @var array<int, string>
     */
    public array $sites = [];

    /**
     * Whether entries can be nested under a parent entry — Statamic's
     * "Structured" collection setting. Enables the {parent_uri} route
     * variable.
     */
    public bool $structured = false;

    /**
     * Whether entries can be manually reordered (drag-and-drop).
     */
    public bool $orderable = false;

    /**
     * Whether the "Order" field shows in the entry form sidebar.
     */
    public bool $visibleOrder = true;

    /**
     * Whether the entry form's live preview pane is available for this
     * collection.
     */
    public bool $livePreview = false;

    /**
     * Whether entries in this collection get a "Schema" tab for attaching
     * schema.org JSON-LD blocks. See Collection::schemaEnabled().
     */
    public bool $schemaEnabled = false;

    /**
     * Whether entries in this collection get an "SEO" tab. See
     * Collection::seoEnabled().
     */
    public bool $seoEnabled = false;

    /**
     * Maximum nesting depth for the tree, as a string for the number input.
     * Blank means unlimited.
     */
    public string $maxDepth = '';

    /**
     * Whether a structured collection requires exactly one root entry.
     */
    public bool $expectsRoot = false;

    /**
     * Where the entry form sends you after saving: 'listing', 'editing', or
     * 'create_another'. See Collection::saveAction().
     */
    public string $saveAction = 'listing';

    #[Computed]
    public function collections()
    {
        return Collection::orderBy('title')->get();
    }

    #[Computed]
    public function availableSites()
    {
        return Site::orderBy('order')->get();
    }

    #[Computed]
    public function defaultSiteHandle(): ?string
    {
        return $this->availableSites->first()?->handle;
    }

    public function create(): void
    {
        auth()->user()->authorizePermission('configure collections');

        $this->reset(['editingId', 'handle', 'title', 'route', 'routes', 'structured', 'orderable', 'visibleOrder', 'livePreview', 'schemaEnabled', 'seoEnabled', 'maxDepth', 'expectsRoot', 'saveAction']);
        $this->routeMode = 'single';
        $this->sites = $this->availableSites->pluck('handle')->all();
        Flux::modal('collection-form')->show();
    }

    public function edit(int $id): void
    {
        auth()->user()->authorizePermission('configure collections');

        $collection = Collection::findOrFail($id);

        $this->editingId = $collection->id;
        $this->handle = $collection->handle;
        $this->title = $collection->title;
        $this->routeMode = $collection->routeMode();
        $this->route = (string) ($collection->settings['route'] ?? '');
        $this->routes = $collection->routes();
        $this->sites = $collection->configuredSites() ?: $this->availableSites->pluck('handle')->all();
        $this->structured = $collection->structured();
        $this->orderable = $collection->orderable();
        $this->visibleOrder = $collection->visibleOrder();
        $this->livePreview = $collection->livePreview();
        $this->schemaEnabled = $collection->schemaEnabled();
        $this->seoEnabled = $collection->seoEnabled();
        $this->maxDepth = (string) ($collection->maxDepth() ?? '');
        $this->expectsRoot = $collection->expectsRoot();
        $this->saveAction = $collection->saveAction();

        Flux::modal('collection-form')->show();
    }

    public function regenerateHandle(): void
    {
        $this->handle = Str::slug($this->title, '_');
    }

    /**
     * Turning off "structured" also turns off the sub-settings that only
     * make sense for a tree.
     */
    public function updatedStructured(bool $value): void
    {
        if (! $value) {
            $this->maxDepth = '';
            $this->expectsRoot = false;
        }
    }

    /**
     * Keeps the default site checked and forces it back in if a stale
     * client payload ever tried to drop it — it can't be unchecked in the
     * UI. Also falls back to a single route once there's only one site left
     * to route, since "per-site" is meaningless for one site.
     */
    public function updatedSites(): void
    {
        $this->sites = array_values(array_unique([...$this->sites, ...array_filter([$this->defaultSiteHandle])]));

        if (count($this->sites) <= 1) {
            $this->routeMode = 'single';
        }
    }

    public function save(): void
    {
        auth()->user()->authorizePermission('configure collections');

        $siteHandles = $this->availableSites->pluck('handle')->all();

        $validated = $this->validate([
            'handle' => ['required', 'string', 'max:255', Rule::unique('collections', 'handle')->ignore($this->editingId)],
            'title' => ['required', 'string', 'max:255'],
            'routeMode' => ['required', Rule::in(['single', 'per_site'])],
            'route' => ['nullable', 'string', 'max:255'],
            'routes' => ['array'],
            'routes.*' => ['nullable', 'string', 'max:255'],
            'sites' => ['array'],
            'sites.*' => [Rule::in($siteHandles)],
            'structured' => ['boolean'],
            'orderable' => ['boolean'],
            'visibleOrder' => ['boolean'],
            'livePreview' => ['boolean'],
            'schemaEnabled' => ['boolean'],
            'seoEnabled' => ['boolean'],
            'maxDepth' => ['nullable', 'integer', 'min:1'],
            'expectsRoot' => ['boolean'],
            'saveAction' => ['required', Rule::in(['listing', 'editing', 'create_another'])],
        ]);

        // Every site selected is the same as no restriction at all.
        $sites = count($validated['sites']) === count($siteHandles) ? [] : $validated['sites'];

        // Per-site only makes sense with more than one site checked.
        $routeMode = count($validated['sites']) > 1 ? $validated['routeMode'] : 'single';

        if ($routeMode === 'per_site') {
            // Drop route entries for sites that are no longer checked.
            $routes = array_filter(
                $validated['routes'],
                fn ($route, $site) => $route && in_array($site, $validated['sites'], true),
                ARRAY_FILTER_USE_BOTH,
            );
            $route = null;
        } else {
            $routes = [];
            $route = $validated['route'] ?: null;
        }

        Collection::updateOrCreate(['id' => $this->editingId], [
            'handle' => $validated['handle'],
            'title' => $validated['title'],
            'settings' => [
                'route_mode' => $routeMode,
                'route' => $route,
                'routes' => $routes,
                'sites' => $sites,
                'structured' => $validated['structured'],
                'orderable' => $validated['orderable'],
                'visible_order' => $validated['visibleOrder'],
                'live_preview' => $validated['livePreview'],
                'schema_enabled' => $validated['schemaEnabled'],
                'seo_enabled' => $validated['seoEnabled'],
                'max_depth' => $validated['structured'] && $validated['maxDepth'] !== '' ? (int) $validated['maxDepth'] : null,
                'expects_root' => $validated['structured'] && $validated['expectsRoot'],
                'save_action' => $validated['saveAction'],
            ],
        ]);

        Flux::modal('collection-form')->close();
        Flux::toast(variant: 'success', text: __('Collection saved.'));

        unset($this->collections);
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission('configure collections');

        $collection = Collection::findOrFail($id);

        $entryCount = Entry::where('collection', $collection->handle)->count();

        if ($entryCount > 0) {
            Flux::toast(
                variant: 'danger',
                text: __('Cannot delete ":title" — it still has :count entries. Delete those first.', [
                    'title' => $collection->title,
                    'count' => $entryCount,
                ]),
            );

            return;
        }

        $collection->delete();

        Flux::toast(variant: 'success', text: __('Collection deleted.'));

        unset($this->collections);
    }

    public function render(): View
    {
        return view('collections::livewire.collection-manager');
    }
}
