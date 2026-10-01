<?php

namespace App\Modules\Collections\Livewire;

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Collections\Services\CollectionTreeService;
use App\Modules\Sites\Models\Site;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Computed;
use Livewire\Component;

class EntryManager extends Component
{
    const DEFAULT_SITE = 'indonesia';

    public Collection $collection;

    public string $search = '';

    public string $siteFilter = '';

    /**
     * Ids checked in the bulk-select column — only offered for a flat
     * collection (neither Structured nor Orderable, see
     * EntryManager::bulkActionsAvailable()), since drag-and-drop reordering
     * and a structured tree both need that same first column for their own
     * controls. See bulkDelete()/bulkUpdateStatus().
     *
     * @var array<int, string>
     */
    public array $selected = [];

    public function mount(Collection $collection): void
    {
        $this->collection = $collection;

        auth()->user()->authorizePermission("view {$this->collection->handle} entries");

        $this->siteFilter = Site::orderBy('order')->get()
            ->first(fn (Site $site): bool => auth()->user()->canAccessSite($site->handle))?->handle ?? '';
    }

    public function bulkActionsAvailable(): bool
    {
        return ! $this->collection->structured() && ! $this->collection->orderable();
    }

    public function updatedSearch(): void
    {
        $this->selected = [];
    }

    public function updatedSiteFilter(): void
    {
        $this->selected = [];
    }

    /**
     * Checks/unchecks every currently-listed (search/site-filtered) entry
     * at once.
     */
    public function toggleSelectAll(): void
    {
        $ids = $this->entries->pluck('id')->all();

        $this->selected = $ids !== [] && count(array_intersect($this->selected, $ids)) === count($ids)
            ? []
            : $ids;
    }

    /**
     * The entry with order = 1 is treated as this collection's homepage —
     * pinned first in the listing, regardless of the sort below. For a
     * structured collection, entries are instead listed as a flattened
     * tree — each entry immediately followed by its children — with
     * siblings at every level ordered by their own "order" (entries
     * without one last), so it stays consistent with the homepage rule.
     * Each entry gets transient `depth` and `parentTitle` attributes for
     * the view to indent and label it with.
     */
    #[Computed]
    public function entries()
    {
        $query = $this->baseQuery();

        if (! $this->collection->structured()) {
            return $query->orderByRaw('`order` != 1')->orderByDesc('updated_at')->get();
        }

        $entries = $query->get();
        $trees = app(CollectionTreeService::class);
        $parentIds = $entries->mapWithKeys(fn (Entry $entry): array => [
            $entry->id => $trees->parentIdOf($this->collection->handle, $entry->site, $entry->id),
        ]);

        return $this->flattenTree($entries, $parentIds, null, 0);
    }

    /**
     * A nested tree of entries for a "Structured" collection — each entry
     * carries a "children" attribute holding its own child entries,
     * recursively, sorted the same way as entries()'s flattened list. Used
     * by the drag-and-drop tree view, which needs real nesting (rather than
     * entries()'s flat, depth-annotated list) so each parent's children can
     * be their own independent drag-and-drop group.
     */
    #[Computed]
    public function entryTree()
    {
        $entries = $this->baseQuery()->get();
        $trees = app(CollectionTreeService::class);
        $parentIds = $entries->mapWithKeys(fn (Entry $entry): array => [
            $entry->id => $trees->parentIdOf($this->collection->handle, $entry->site, $entry->id),
        ]);

        return $this->buildTree($entries, $parentIds, null);
    }

    protected function baseQuery()
    {
        return Entry::where('collection', $this->collection->handle)
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('slug', 'like', "%{$this->search}%")
                        ->orWhere('data->title', 'like', "%{$this->search}%");
                });
            })
            ->when($this->siteFilter !== '', fn ($query) => $query->where('site', $this->siteFilter));
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Entry>  $entries
     * @param  \Illuminate\Support\Collection<string, ?string>  $parentIds
     * @return \Illuminate\Support\Collection<int, Entry>
     */
    protected function flattenTree($entries, $parentIds, ?string $parentId, int $depth)
    {
        $siblings = $this->sortedSiblings($entries, $parentIds, $parentId);

        $parentTitle = $parentId ? $entries->firstWhere('id', $parentId)?->data['title'] ?? null : null;

        $result = collect();

        foreach ($siblings as $entry) {
            $entry->setAttribute('depth', $depth);
            $entry->setAttribute('parentTitle', $parentTitle);
            $result->push($entry);
            $result = $result->merge($this->flattenTree($entries, $parentIds, $entry->id, $depth + 1));
        }

        return $result;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Entry>  $entries
     * @param  \Illuminate\Support\Collection<string, ?string>  $parentIds
     * @return \Illuminate\Support\Collection<int, Entry>
     */
    protected function buildTree($entries, $parentIds, ?string $parentId)
    {
        return $this->sortedSiblings($entries, $parentIds, $parentId)
            ->map(function (Entry $entry) use ($entries, $parentIds) {
                $entry->setAttribute('children', $this->buildTree($entries, $parentIds, $entry->id));

                return $entry;
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Entry>  $entries
     * @param  \Illuminate\Support\Collection<string, ?string>  $parentIds
     * @return \Illuminate\Support\Collection<int, Entry>
     */
    protected function sortedSiblings($entries, $parentIds, ?string $parentId)
    {
        return $entries
            ->filter(fn (Entry $entry): bool => ($parentIds[$entry->id] ?? null) === $parentId)
            ->sort(function (Entry $a, Entry $b): int {
                $byOrder = ($a->order ?? PHP_INT_MAX) <=> ($b->order ?? PHP_INT_MAX);

                return $byOrder !== 0 ? $byOrder : $b->updated_at <=> $a->updated_at;
            })
            ->values();
    }

    public function reorderRoot($id, $position): void
    {
        $this->reorder((string) $id, (int) $position, null);
    }

    public function reorderChildren($id, $position, $parentId): void
    {
        $this->reorder((string) $id, (int) $position, (string) $parentId);
    }

    /**
     * Renumbers the dragged entry's sibling group (root entries, or one
     * parent's children) so their `order` matches the new drag position.
     * Reordering is confined to a single site and to the dragged entry's
     * existing parent — each parent's children render as their own
     * independent drag-and-drop group (see entry-tree-row.blade.php), so an
     * entry can never be dragged out from under its parent. The root
     * group's first position becomes order 1, which EntryUrlResolver and
     * this listing both treat as the site's homepage; child groups start
     * numbering at 2 so a child can never accidentally become the homepage.
     */
    protected function reorder(string $id, int $position, ?string $parentId): void
    {
        auth()->user()->authorizePermission("reorder {$this->collection->handle} entries");

        if (! $this->collection->orderable() || $this->siteFilter === '') {
            return;
        }

        $all = Entry::where('collection', $this->collection->handle)->where('site', $this->siteFilter)->get();
        $moved = $all->firstWhere('id', $id);

        if ($moved === null) {
            return;
        }

        if ($this->collection->structured()) {
            $trees = app(CollectionTreeService::class);
            $siblings = $all->filter(fn (Entry $entry): bool => $trees->parentIdOf($this->collection->handle, $this->siteFilter, $entry->id) === $parentId);
        } else {
            $siblings = $all;
        }

        $siblings = $siblings->sortBy(fn (Entry $entry) => $entry->order ?? PHP_INT_MAX)
            ->reject(fn (Entry $entry): bool => $entry->id === $id)
            ->values();
        $siblings->splice(min($position, $siblings->count()), 0, [$moved]);

        $base = $parentId === null ? 1 : 2;

        foreach ($siblings as $index => $entry) {
            $entry->update(['order' => $index + $base]);
        }

        unset($this->entries, $this->entryTree);
    }

    #[Computed]
    public function availableSites()
    {
        return Site::orderBy('order')
            ->get()
            ->filter(fn (Site $site): bool => auth()->user()->canAccessSite($site->handle))
            ->values();
    }

    /**
     * The first site this user may access — used as the target for the
     * "New entry" button, which always needs a concrete site. Empty when the
     * user has no site access at all (then the button is hidden).
     */
    public function createSite(): string
    {
        return $this->availableSites->first()?->handle ?? '';
    }

    /**
     * All blueprints an entry in this collection is allowed to use: every
     * blueprint namespaced to this collection (e.g. "collections.pages").
     */
    #[Computed]
    public function availableBlueprints()
    {
        return Blueprint::where('namespace', "collections.{$this->collection->handle}")
            ->orderBy('handle')
            ->get();
    }

    /**
     * This entry's other-language versions — every entry in the same
     * "origin family" (its origin, and every entry translated from it),
     * excluding itself. Mirrors TermForm::siblingTermForSite()'s
     * origin/origin_id relationship.
     *
     * @return \Illuminate\Support\Collection<int, Entry>
     */
    protected function localeSiblings(Entry $entry)
    {
        $originId = $entry->origin_id ?? $entry->id;

        return Entry::where('collection', $this->collection->handle)
            ->where('id', '!=', $entry->id)
            ->where(fn ($query) => $query->where('id', $originId)->orWhere('origin_id', $originId))
            ->get();
    }

    /**
     * Number of other-language versions this entry has — shown in the
     * delete-confirmation modal so the "also delete" checkbox isn't a
     * mystery when there's nothing else to delete.
     */
    public function localeSiblingCount(Entry $entry): int
    {
        return $this->localeSiblings($entry)->count();
    }

    public function delete(string $id, bool $deleteLocaleSiblings = true): void
    {
        $user = auth()->user();
        $user->authorizePermission("delete {$this->collection->handle} entries");

        $entry = Entry::where('collection', $this->collection->handle)->findOrFail($id);

        if (! $entry->canBeDeletedBy($user)) {
            abort(403);
        }

        $siblings = $this->localeSiblings($entry);

        if ($deleteLocaleSiblings) {
            abort_unless($siblings->every(fn (Entry $sibling): bool => $sibling->canBeDeletedBy($user)), 403);

            foreach ($siblings as $sibling) {
                if ($this->collection->structured()) {
                    app(CollectionTreeService::class)->removeEntry($this->collection->handle, $sibling->site, $sibling->id);
                }

                $sibling->delete();
            }
        }

        if ($this->collection->structured()) {
            app(CollectionTreeService::class)->removeEntry($this->collection->handle, $entry->site, $id);
        }

        $entry->delete();

        Flux::toast(variant: 'success', text: __('Entry deleted.'));

        unset($this->entries, $this->entryTree);
    }

    /**
     * Deletes every checked entry (and, same as the single-entry delete,
     * its other-language versions) — only offered for a flat collection,
     * see bulkActionsAvailable().
     */
    public function bulkDelete(): void
    {
        $user = auth()->user();
        $user->authorizePermission("delete {$this->collection->handle} entries");

        if (! $this->bulkActionsAvailable()) {
            return;
        }

        foreach ($this->selected as $id) {
            $entry = Entry::where('collection', $this->collection->handle)->find($id);

            if (! $entry) {
                continue;
            }

            abort_unless($entry->canBeDeletedBy($user), 403);
            abort_unless($this->localeSiblings($entry)->every(fn (Entry $sibling): bool => $sibling->canBeDeletedBy($user)), 403);

            foreach ($this->localeSiblings($entry) as $sibling) {
                $sibling->delete();
            }

            $entry->delete();
        }

        $count = count($this->selected);
        $this->selected = [];
        unset($this->entries);

        Flux::toast(variant: 'success', text: trans_choice(':count entry deleted.|:count entries deleted.', $count, ['count' => $count]));
    }

    /**
     * Whether the currently selected entries (with their locale siblings)
     * can all be deleted by this user — gates the bulk Delete button, so a
     * selection mixing one's own and someone else's entries shows the
     * button only when deletion is actually possible for every one of them.
     */
    public function canBulkDelete(): bool
    {
        $user = auth()->user();

        if (! $user->hasPermission("delete {$this->collection->handle} entries")) {
            return false;
        }

        return Entry::where('collection', $this->collection->handle)
            ->whereIn('id', $this->selected)
            ->get()
            ->every(fn (Entry $entry): bool => $entry->canBeDeletedBy($user)
                && $this->localeSiblings($entry)->every(fn (Entry $sibling): bool => $sibling->canBeDeletedBy($user)));
    }

    /**
     * Sets every checked entry's status at once — only offered for a flat
     * collection, see bulkActionsAvailable(). Moving TO "published"
     * additionally needs the collection's own publish permission, mirroring
     * EntryForm::save(); moving away from it (or between draft/need_review)
     * only needs the base edit permission already required to reach this
     * page's bulk actions at all.
     */
    public function bulkUpdateStatus(string $status): void
    {
        auth()->user()->authorizePermission("edit {$this->collection->handle} entries");

        if (! array_key_exists($status, EntryForm::STATUSES) || ! $this->bulkActionsAvailable()) {
            return;
        }

        if (in_array($status, ['published', 'scheduled'], true)) {
            $user = auth()->user();
            $user->authorizePermission("publish {$this->collection->handle} entries");

            // Bulk-publishing someone else's entry needs the "publish other
            // authors" variant, mirroring EntryForm::save().
            $hasForeignEntry = Entry::where('collection', $this->collection->handle)
                ->whereIn('id', $this->selected)
                ->get()
                ->contains(fn (Entry $entry): bool => ! $entry->ownedBy($user));

            if ($hasForeignEntry) {
                $user->authorizePermission("publish other authors {$this->collection->handle} entries");
            }
        }

        // Updated one model at a time (not a single mass ->update() query)
        // so Entry::booted()'s "saved" hook fires for each — a mass update
        // bypasses Eloquent model events entirely, which would leave
        // sitemap.xml out of sync with entries that just left/entered
        // "published" (see SitemapSyncer).
        Entry::where('collection', $this->collection->handle)
            ->whereIn('id', $this->selected)
            ->get()
            ->each(function (Entry $entry) use ($status): void {
                // Same "published"/"scheduled" resolution as EntryForm::save()
                // — a future-dated entry stays/becomes "scheduled" no matter
                // which of the two was bulk-picked.
                $resolved = in_array($status, ['published', 'scheduled'], true)
                    ? ($entry->date && Carbon::parse($entry->date)->isFuture() ? 'scheduled' : 'published')
                    : $status;

                $entry->update(['status' => $resolved, 'published' => $resolved === 'published']);
            });

        $count = count($this->selected);
        $this->selected = [];
        unset($this->entries);

        Flux::toast(variant: 'success', text: trans_choice(':count entry updated.|:count entries updated.', $count, ['count' => $count]));
    }

    /**
     * Whether $status is applicable to every currently selected entry by
     * this user — gates the published/scheduled options in the bulk status
     * dropdown, so entries owned by someone else aren't offered a publish
     * action the backend would refuse.
     */
    public function canBulkUpdateStatus(string $status): bool
    {
        $user = auth()->user();

        if (! $user->hasPermission("edit {$this->collection->handle} entries")) {
            return false;
        }

        if (! in_array($status, ['published', 'scheduled'], true)) {
            return true;
        }

        if (! $user->hasPermission("publish {$this->collection->handle} entries")) {
            return false;
        }

        return Entry::where('collection', $this->collection->handle)
            ->whereIn('id', $this->selected)
            ->get()
            ->every(fn (Entry $entry): bool => $entry->ownedBy($user) || $user->hasPermission("publish other authors {$this->collection->handle} entries"));
    }

    public function render(): View
    {
        return view('collections::livewire.entry-manager', [
            'defaultSite' => self::DEFAULT_SITE,
            'statuses' => EntryForm::STATUSES,
            'orderable' => $this->collection->orderable() && $this->search === '' && $this->siteFilter !== '',
        ])->title(__(':collection entries', ['collection' => $this->collection->title]));
    }
}
