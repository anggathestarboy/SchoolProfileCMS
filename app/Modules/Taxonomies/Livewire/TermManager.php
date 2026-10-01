<?php

namespace App\Modules\Taxonomies\Livewire;

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Sites\Models\Site;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class TermManager extends Component
{
    const DEFAULT_SITE = 'indonesia';

    public Taxonomy $taxonomy;

    public string $search = '';

    public string $siteFilter = '';

    public function mount(Taxonomy $taxonomy): void
    {
        auth()->user()->authorizePermission("view {$taxonomy->handle} terms");

        $this->taxonomy = $taxonomy;
        $this->siteFilter = $this->defaultSiteHandle() ?? '';
    }

    /**
     * The site new terms default to and the list is initially filtered
     * by — the first of the taxonomy's configured sites the user may access,
     * or the first accessible site overall when the taxonomy has none
     * configured.
     */
    public function defaultSiteHandle(): ?string
    {
        $configuredSites = $this->taxonomy->sites ?? [];

        return Site::orderBy('order')
            ->when($configuredSites !== [], fn ($query) => $query->whereIn('handle', $configuredSites))
            ->get()
            ->first(fn (Site $site): bool => auth()->user()->canAccessSite($site->handle))?->handle;
    }

    /**
     * The site the "New term" button targets — the first site this user may
     * access (empty when they have no site access, hiding the button).
     */
    public function createSite(): string
    {
        return $this->availableSites->first()?->handle ?? '';
    }

    #[Computed]
    public function terms()
    {
        return TaxonomyTerm::where('taxonomy', $this->taxonomy->handle)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('slug', 'like', "%{$this->search}%")
                        ->orWhere('data->title', 'like', "%{$this->search}%");
                });
            })
            ->when($this->siteFilter !== '', fn ($query) => $query->where('site', $this->siteFilter))
            ->orderByDesc('updated_at')
            ->get();
    }

    #[Computed]
    public function availableSites()
    {
        $configuredSites = $this->taxonomy->sites ?? [];

        return Site::orderBy('order')
            ->when($configuredSites !== [], fn ($query) => $query->whereIn('handle', $configuredSites))
            ->get()
            ->filter(fn (Site $site): bool => auth()->user()->canAccessSite($site->handle))
            ->values();
    }

    /**
     * All blueprints a term in this taxonomy is allowed to use: the
     * taxonomy's explicitly picked blueprint (if any) plus every blueprint
     * namespaced to this taxonomy (e.g. "taxonomies.news_categories").
     */
    #[Computed]
    public function availableBlueprints()
    {
        $explicitHandle = $this->taxonomy->settings['blueprint'] ?? null;

        return Blueprint::where('namespace', "taxonomies.{$this->taxonomy->handle}")
            ->when($explicitHandle, fn ($query) => $query->orWhere('handle', $explicitHandle))
            ->orderBy('handle')
            ->get()
            ->unique('id')
            ->values();
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission("delete {$this->taxonomy->handle} terms");

        TaxonomyTerm::where('taxonomy', $this->taxonomy->handle)->findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Term deleted.'));

        unset($this->terms);
    }

    public function render(): View
    {
        return view('taxonomies::livewire.term-manager', [
            'defaultSite' => $this->defaultSiteHandle() ?? self::DEFAULT_SITE,
        ])->title(__(':taxonomy terms', ['taxonomy' => $this->taxonomy->title]));
    }
}
