<?php

namespace App\Modules\Globals\Livewire;

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Globals\Models\GlobalSet;
use App\Modules\Globals\Models\GlobalSetVariable;
use App\Modules\Sites\Models\Site;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Globals')]
class GlobalSetManager extends Component
{
    public ?int $editingId = null;

    public string $handle = '';

    public string $title = '';

    public string $blueprint = '';

    /**
     * Whether saving this (new) global set should also create a blueprint
     * for it, namespaced "globals.{handle}", instead of requiring one to be
     * picked from $blueprint. Only relevant on create — an existing global
     * set already has its blueprint decided. Defaults on, since a global
     * set with no fields at all isn't useful yet.
     */
    public bool $createBlueprint = true;

    /**
     * Which sites (languages) this global set has values for. Empty means
     * single-language: values are stored under a null locale and no site
     * switcher shows on the values page. Two or more shows a site switcher
     * on the values page (see GlobalSetForm), driven by ?site=... in the URL.
     *
     * @var array<int, string>
     */
    public array $sites = [];

    /**
     * Where the values form goes after saving this global set's values.
     * Mirrors CollectionManager::$saveAction.
     */
    public string $saveAction = 'listing';

    #[Computed]
    public function globalSets()
    {
        return GlobalSet::orderBy('title')->get();
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
     * the first site by order (shown as #1 on the Sites page). It's forced
     * checked in the Languages list and can't be unselected, since a global
     * set's origin language is always the default site.
     */
    #[Computed]
    public function defaultSiteHandle(): ?string
    {
        return $this->availableSites->first()?->handle;
    }

    public function create(): void
    {
        auth()->user()->authorizePermission('configure globals');

        $this->reset(['editingId', 'handle', 'title', 'blueprint']);
        $this->createBlueprint = true;
        $this->saveAction = 'listing';
        $this->sites = array_values(array_filter([$this->defaultSiteHandle]));
        Flux::modal('global-set-form')->show();
    }

    /**
     * Whether this user may edit a global set's definition (name/handle/
     * blueprints/sites) or its values — the umbrella "configure globals"
     * permission works for every set (its children are granted by the role
     * UI alongside it), or the per-set "edit {handle} globals" permission.
     * Mirrors the GlobalSetForm save action.
     */
    protected function canEdit(GlobalSet $globalSet): bool
    {
        $user = auth()->user();

        return $user->hasPermission('configure globals')
            || $user->hasPermission("edit {$globalSet->handle} globals");
    }

    /**
     * Whether this user may delete a global set. Same shape as canEdit():
     * the umbrella "configure globals" permission or the per-set
     * "delete {handle} globals" permission.
     */
    protected function canDelete(GlobalSet $globalSet): bool
    {
        $user = auth()->user();

        return $user->hasPermission('configure globals')
            || $user->hasPermission("delete {$globalSet->handle} globals");
    }

    public function edit(int $id): void
    {
        $user = auth()->user();
        $globalSet = GlobalSet::findOrFail($id);

        if (! $this->canEdit($globalSet)) {
            abort(403);
        }

        $this->editingId = $globalSet->id;
        $this->handle = $globalSet->handle;
        $this->title = $globalSet->title;
        $this->blueprint = (string) ($globalSet->settings['blueprint'] ?? $globalSet->effectiveBlueprint()?->handle ?? '');
        $this->createBlueprint = false;
        $this->saveAction = $globalSet->saveAction();
        $this->sites = array_values(array_unique(array_merge(
            $globalSet->settings['sites'] ?? [],
            array_filter([$this->defaultSiteHandle])
        )));

        Flux::modal('global-set-form')->show();
    }

    public function regenerateHandle(): void
    {
        $this->handle = Str::slug($this->title, '_');
    }

    public function save(): void
    {
        $user = auth()->user();

        if ($this->editingId) {
            $globalSet = GlobalSet::findOrFail($this->editingId);

            if (! $this->canEdit($globalSet)) {
                abort(403);
            }
        } else {
            $user->authorizePermission('configure globals');
        }

        $siteOrder = $this->availableSites->pluck('handle')->all();
        $this->sites = collect(array_unique(array_merge($this->sites, array_filter([$this->defaultSiteHandle]))))
            ->sortBy(fn (string $handle): int => array_search($handle, $siteOrder, true))
            ->values()
            ->all();

        $validated = $this->validate([
            'handle' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('global_sets', 'handle')->ignore($this->editingId)],
            'title' => ['required', 'string', 'max:255'],
            'blueprint' => [$this->createBlueprint ? 'exclude' : 'nullable', 'string', Rule::exists('blueprints', 'handle')],
            'sites' => ['array'],
            'sites.*' => [Rule::in($siteOrder)],
            'saveAction' => ['required', Rule::in(['listing', 'editing'])],
        ]);

        $globalSet = GlobalSet::updateOrCreate(['id' => $this->editingId], [
            'handle' => $validated['handle'],
            'title' => $validated['title'],
            'settings' => ['blueprint' => $validated['blueprint'] ?? null, 'sites' => $validated['sites'], 'save_action' => $validated['saveAction']],
        ]);

        $locales = $validated['sites'] !== [] ? $validated['sites'] : [null];

        foreach ($locales as $locale) {
            GlobalSetVariable::firstOrCreate(['handle' => $globalSet->handle, 'locale' => $locale], ['data' => []]);
        }

        $blueprintCreated = false;

        if ($this->createBlueprint) {
            $blueprint = Blueprint::firstOrCreate(
                ['namespace' => "globals.{$globalSet->handle}", 'handle' => $globalSet->handle],
                ['data' => ['tabs' => [['handle' => 'main', 'display' => 'Main', 'sections' => []]], 'fields' => []]]
            );
            $blueprintCreated = $blueprint->wasRecentlyCreated;
        }

        Flux::modal('global-set-form')->close();
        unset($this->globalSets);

        if ($blueprintCreated) {
            Flux::toast(variant: 'success', text: __('Global set saved — now add fields to its blueprint below.'));
            $this->redirectRoute('blueprints.index', navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: __('Global set saved.'));
    }

    public function delete(int $id): void
    {
        $globalSet = GlobalSet::findOrFail($id);

        if (! $this->canDelete($globalSet)) {
            abort(403);
        }

        GlobalSetVariable::where('handle', $globalSet->handle)->delete();
        $globalSet->delete();

        Flux::toast(variant: 'success', text: __('Global set deleted.'));

        unset($this->globalSets);
    }

    public function render(): View
    {
        return view('globals::livewire.global-set-manager');
    }
}
