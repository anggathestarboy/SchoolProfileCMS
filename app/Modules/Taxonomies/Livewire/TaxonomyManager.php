<?php

namespace App\Modules\Taxonomies\Livewire;

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Sites\Models\Site;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Taxonomies')]
class TaxonomyManager extends Component
{
    public ?int $editingId = null;

    public string $handle = '';

    public string $title = '';

    public string $blueprint = '';

    /**
     * Whether saving this (new) taxonomy should also create a blueprint for
     * it, namespaced "taxonomies.{handle}", instead of requiring one to be
     * picked from $blueprint. Only relevant on create. Mirrors
     * GlobalSetManager::$createBlueprint.
     */
    public bool $createBlueprint = true;

    /**
     * Which sites (languages) this taxonomy's terms have values for. Empty
     * means single-language. Mirrors GlobalSetManager::$sites.
     *
     * @var array<int, string>
     */
    public array $sites = [];

    /**
     * Where the term form goes after saving one of this taxonomy's terms.
     * Mirrors CollectionManager::$saveAction.
     */
    public string $saveAction = 'listing';

    #[Computed]
    public function taxonomies()
    {
        return Taxonomy::orderBy('title')->get();
    }

    #[Computed]
    public function availableBlueprints()
    {
        return Blueprint::orderBy('namespace')->orderBy('handle')->get();
    }

    #[Computed]
    public function availableSites()
    {
        return Site::orderBy('order')->get();
    }

    /**
     * The site treated as this install's default/origin language — always
     * the first site by order. Forced checked in the Languages list and
     * can't be unselected, since a taxonomy's origin language is always the
     * default site. Mirrors GlobalSetManager::defaultSiteHandle().
     */
    #[Computed]
    public function defaultSiteHandle(): ?string
    {
        return $this->availableSites->first()?->handle;
    }

    public function create(): void
    {
        auth()->user()->authorizePermission('configure taxonomies');

        $this->reset(['editingId', 'handle', 'title', 'blueprint']);
        $this->createBlueprint = true;
        $this->saveAction = 'listing';
        $this->sites = array_values(array_filter([$this->defaultSiteHandle]));
        Flux::modal('taxonomy-form')->show();
    }

    public function edit(int $id): void
    {
        auth()->user()->authorizePermission('configure taxonomies');

        $taxonomy = Taxonomy::findOrFail($id);

        $this->editingId = $taxonomy->id;
        $this->handle = $taxonomy->handle;
        $this->title = $taxonomy->title;
        $this->blueprint = (string) ($taxonomy->settings['blueprint'] ?? $taxonomy->effectiveBlueprint()?->handle ?? '');
        $this->createBlueprint = false;
        $this->saveAction = $taxonomy->saveAction();
        $this->sites = array_values(array_unique(array_merge(
            $taxonomy->sites ?? [],
            array_filter([$this->defaultSiteHandle])
        )));

        Flux::modal('taxonomy-form')->show();
    }

    public function regenerateHandle(): void
    {
        $this->handle = Str::slug($this->title, '_');
    }

    public function save(): void
    {
        auth()->user()->authorizePermission('configure taxonomies');

        $siteOrder = $this->availableSites->pluck('handle')->all();
        $this->sites = collect(array_unique(array_merge($this->sites, array_filter([$this->defaultSiteHandle]))))
            ->sortBy(fn (string $handle): int => array_search($handle, $siteOrder, true))
            ->values()
            ->all();

        $validated = $this->validate([
            'handle' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('taxonomies', 'handle')->ignore($this->editingId)],
            'title' => ['required', 'string', 'max:255'],
            'blueprint' => [$this->createBlueprint ? 'exclude' : 'nullable', 'string', Rule::exists('blueprints', 'handle')],
            'sites' => ['array'],
            'sites.*' => [Rule::in($siteOrder)],
            'saveAction' => ['required', Rule::in(['listing', 'editing', 'create_another'])],
        ]);

        $taxonomy = Taxonomy::updateOrCreate(['id' => $this->editingId], [
            'handle' => $validated['handle'],
            'title' => $validated['title'],
            'sites' => $validated['sites'],
            'settings' => ['blueprint' => $validated['blueprint'] ?? null, 'save_action' => $validated['saveAction']],
        ]);

        $blueprintCreated = false;

        if ($this->createBlueprint) {
            $blueprint = Blueprint::firstOrCreate(
                ['namespace' => "taxonomies.{$taxonomy->handle}", 'handle' => $taxonomy->handle],
                ['data' => ['tabs' => [['handle' => 'main', 'display' => 'Main', 'sections' => []]], 'fields' => []]]
            );
            $blueprintCreated = $blueprint->wasRecentlyCreated;
        }

        Flux::modal('taxonomy-form')->close();
        unset($this->taxonomies);

        if ($blueprintCreated) {
            Flux::toast(variant: 'success', text: __('Taxonomy saved — now add fields to its blueprint below.'));
            $this->redirectRoute('blueprints.index', navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: __('Taxonomy saved.'));
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission('configure taxonomies');

        $taxonomy = Taxonomy::findOrFail($id);

        $termCount = TaxonomyTerm::where('taxonomy', $taxonomy->handle)->count();

        if ($termCount > 0) {
            Flux::toast(
                variant: 'danger',
                text: __('Cannot delete ":title" — it still has :count terms. Delete those first.', [
                    'title' => $taxonomy->title,
                    'count' => $termCount,
                ]),
            );

            return;
        }

        $taxonomy->delete();

        Flux::toast(variant: 'success', text: __('Taxonomy deleted.'));

        unset($this->taxonomies);
    }

    public function render(): View
    {
        return view('taxonomies::livewire.taxonomy-manager');
    }
}
