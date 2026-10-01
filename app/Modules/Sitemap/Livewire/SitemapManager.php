<?php

namespace App\Modules\Sitemap\Livewire;

use App\Modules\Collections\Models\Entry;
use App\Modules\Sitemap\Models\Sitemap;
use App\Modules\Sitemap\Services\SitemapSyncer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Read-only listing of the sitemaps table (see SitemapSyncer, which keeps it
 * in sync automatically on every entry save/delete) — lets an editor spot-
 * check what's actually in sitemap.xml, toggle a row's active flag, or force
 * a full resync without waiting for every entry to be re-saved.
 */
#[Title('Sitemap')]
class SitemapManager extends Component
{
    public string $search = '';

    #[Computed]
    public function urls()
    {
        return Sitemap::when($this->search !== '', fn ($query) => $query->where('url', 'like', "%{$this->search}%"))
            ->orderBy('url')
            ->get();
    }

    public function toggleActive(int $id): void
    {
        $url = Sitemap::findOrFail($id);
        $url->update(['is_active' => ! $url->is_active]);

        unset($this->urls);
    }

    public function delete(int $id): void
    {
        Sitemap::findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Sitemap entry deleted.'));

        unset($this->urls);
    }

    /**
     * Re-syncs every entry's sitemap row from scratch — useful after
     * changing a collection's route template or SEO settings, since those
     * don't themselves re-save every affected entry.
     */
    public function regenerate(): void
    {
        $syncer = app(SitemapSyncer::class);

        Entry::chunk(200, function ($entries) use ($syncer) {
            $entries->each(fn (Entry $entry) => $syncer->sync($entry));
        });

        unset($this->urls);

        Flux::toast(variant: 'success', text: __('Sitemap regenerated.'));
    }

    public function render(): View
    {
        return view('sitemap::livewire.sitemap-manager');
    }
}
