<?php

namespace App\Modules\Collections\Livewire;

use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\Fieldset;
use App\Modules\Blueprints\Support\EvaluatesFieldConditions;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Collections\Services\CollectionTreeService;
use App\Modules\Collections\Services\EntryUrlResolver;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Sites\Models\Site;
use App\Modules\SiteSettings\Support\HasSchemaBlocks;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Full-page create/edit form for an entry, mirroring Statamic's CP routes:
 * create at collections/{collection}/entries/create/{site}?blueprint=...,
 * edit at collections/{collection}/entries/{entry}.
 */
class EntryForm extends Component
{
    use EvaluatesFieldConditions, HasSchemaBlocks;

    const DEFAULT_SITE = 'indonesia';

    /**
     * @var array<string, string>
     */
    const STATUSES = [
        'published' => 'Published',
        'scheduled' => 'Scheduled',
        'need_review' => 'Need Review',
        'draft' => 'Draft',
    ];

    const SEO_ROBOTS_OPTIONS = [
        'index' => 'Index',
        'follow' => 'Follow',
        'noindex' => 'No Index',
        'nofollow' => 'No Follow',
        'noarchive' => 'No Archive',
        'noimageindex' => 'No Image Index',
        'nosnippet' => 'No Snippet',
    ];

    const SEO_SITEMAP_PRIORITIES = ['0.0', '0.1', '0.2', '0.3', '0.4', '0.5', '0.6', '0.7', '0.8', '0.9', '1.0'];

    const SEO_SITEMAP_CHANGEFREQS = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    /**
     * Which "SEO" tab fields are editable per-locale vs. shared across every
     * locale (edited only from the origin site) — mirrors a blueprint
     * field's own "localizable" flag (see overlayNonLocalizableData()).
     *
     * @var array<string, bool>
     */
    const SEO_LOCALIZABLE = [
        'meta_title' => true,
        'meta_description' => true,
        'meta_canonical' => true,
        'robots' => true,
        'meta_featured_image' => false,
        'meta_author' => false,
        'sitemap_priority' => true,
        'sitemap_changefreq' => true,
    ];

    public Collection $collection;

    public ?string $editingId = null;

    public string $site = self::DEFAULT_SITE;

    public string $title = '';

    public string $slug = '';

    public string $status = 'draft';

    public string $entryDate = '';

    public ?string $order = null;

    public string $blueprintHandle = '';

    /**
     * Which of the selected blueprint's tabs is currently shown — see
     * blueprintTabs().
     */
    #[Url('q')]
    public string $activeTab = 'main';

    /**
     * The entry this one is a localization of — self-referencing, so
     * switching sites can find (or create) the sibling entry for that site.
     */
    public ?string $originId = null;

    /**
     * The id of the entry this one is nested under, for a "Structured"
     * collection (see Collection::structured()). Null means top-level.
     * Tracked separately from the entry's own data, in the Structure
     * module's tree for this collection/site.
     */
    public ?string $parentId = null;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /**
     * This entry's "SEO" tab fields — see Collection::seoEnabled() and
     * Entry::seoFields(). Only meaningful (and only saved) when the
     * collection has the tab enabled.
     *
     * @var array<string, mixed>
     */
    public array $seo = [];

    /**
     * Identifies this editing session's cached preview payload
     * (see refreshPreviewCache()) — stable for the lifetime of this page
     * load, regenerated on the next full page load.
     */
    public string $previewToken = '';

    /**
     * Bumped every time the preview cache is refreshed, so binding it into
     * the preview iframe's src as a query string forces a reload whenever
     * the in-progress form data changes.
     */
    public int $previewRefreshCount = 0;

    public function mount(Collection $collection, ?Entry $entry = null, ?string $site = null): void
    {
        $this->collection = $collection;

        auth()->user()->authorizePermission($entry ? "edit {$collection->handle} entries" : "create {$collection->handle} entries");

        if ($entry) {
            if ($entry->collection !== $collection->handle) {
                throw new NotFoundHttpException;
            }

            if (! $entry->canBeEditedBy(auth()->user())) {
                abort(403);
            }

            $this->editingId = $entry->id;
            $this->site = $entry->site;
            $this->title = (string) ($entry->data['title'] ?? '');
            $this->slug = (string) $entry->slug;
            $this->status = $entry->status ?: 'draft';
            $this->entryDate = $entry->date ? str_replace(' ', 'T', $entry->date) : '';
            $this->order = $entry->order !== null ? (string) $entry->order : '';
            $this->blueprintHandle = $entry->blueprint ?? $this->defaultBlueprintHandle();
            $this->originId = $entry->origin_id;
        } else {
            $this->site = $site ?: self::DEFAULT_SITE;
            $this->blueprintHandle = request()->string('blueprint')->toString() ?: $this->defaultBlueprintHandle();
            $this->originId = request()->string('origin')->toString() ?: null;
        }

        if (! auth()->user()->canAccessSite($this->site)) {
            abort(403);
        }

        if ($entry && $this->collection->structured()) {
            $this->parentId = $this->treeService()->parentIdOf($collection->handle, $this->site, $entry->id);
        }

        $this->fillData($entry);

        if ($this->collection->schemaEnabled()) {
            $this->schemaBlocks = array_map(fn (array $block): array => array_merge($this->defaultSchemaBlock(), $block), $entry?->schemaBlocks() ?? []);
        }

        if ($this->collection->seoEnabled()) {
            $this->seo = $entry?->seoFields() ?? Entry::defaultSeoFields();
        }

        if (! $this->isOriginLocale()) {
            $originData = $this->fillFieldsData($this->blueprintFields(), $this->originEntry()?->data);
            $this->overlayNonLocalizableData($this->blueprintFields(), $this->data, $originData);

            if (! $entry) {
                $this->prefillLocalizableDataFromOrigin($this->blueprintFields(), $this->data, $originData);
            }

            if ($this->collection->seoEnabled()) {
                $originSeo = $this->originEntry()?->seoFields() ?? Entry::defaultSeoFields();

                foreach (self::SEO_LOCALIZABLE as $key => $localizable) {
                    if (! $localizable) {
                        $this->seo[$key] = $originSeo[$key];
                    }
                }
            }

            // A link pointing at the default language's entry shows that
            // same content's entry on this site, so the picker is
            // pre-selected on the translation instead of the first option.
            $this->data = Entry::localizeLinkValues($this->data, $this->blueprintFields(), $this->site);
        }

        if ($this->collection->livePreview()) {
            $this->previewToken = (string) Str::uuid();
            $this->refreshPreviewCache();
        }
    }

    #[Computed]
    public function previewUrl(): ?string
    {
        return $this->previewToken !== '' ? route('collections.preview', ['token' => $this->previewToken]) : null;
    }

    /**
     * Caches this editing session's in-progress (not-yet-saved) form state
     * under previewToken, for PreviewController to render — see that
     * controller and Collection::livePreview(). Called on every field
     * change (see updated()) so the live preview pane stays current without
     * requiring a save.
     */
    protected function refreshPreviewCache(): void
    {
        $this->previewRefreshCount++;

        Cache::put(
            "entry-preview:{$this->previewToken}",
            [
                'collection' => $this->collection->handle,
                'version' => $this->previewRefreshCount,
                'blueprint' => $this->blueprintHandle,
                'site' => $this->site,
                'attributes' => [
                    'id' => $this->editingId,
                    'site' => $this->site,
                    'origin_id' => $this->originId,
                    'status' => $this->status,
                    'slug' => $this->slug,
                    'date' => $this->entryDate ? str_replace('T', ' ', $this->entryDate) : null,
                    'order' => $this->order !== '' && $this->order !== null ? (int) $this->order : null,
                    'collection' => $this->collection->handle,
                    'blueprint' => $this->blueprintHandle,
                    'data' => array_merge($this->data, ['title' => $this->title]),
                ],
            ],
            now()->addHour(),
        );
    }

    /**
     * The collection's configured sites — mirrors TermForm::configuredSites().
     * Empty means every site is available.
     *
     * @return array<int, string>
     */
    protected function configuredSites(): array
    {
        return $this->collection->configuredSites();
    }

    /**
     * The site treated as this collection's default/origin language — the
     * first of its configured sites by order, or the first site overall
     * when none are configured. Same convention as
     * TermForm::defaultSiteHandle().
     */
    protected function defaultSiteHandle(): ?string
    {
        $configuredSites = $this->configuredSites();

        return Site::orderBy('order')
            ->when($configuredSites !== [], fn ($query) => $query->whereIn('handle', $configuredSites))
            ->value('handle');
    }

    public function isOriginLocale(): bool
    {
        $defaultSite = $this->defaultSiteHandle();

        return $defaultSite === null || $this->site === $defaultSite;
    }

    /**
     * The entry currently being edited, or null on create.
     */
    protected function entry(): ?Entry
    {
        return $this->editingId ? Entry::find($this->editingId) : null;
    }

    /**
     * The sibling entry (or this entry itself) on the default/origin site —
     * authoritative source for non-localizable field values.
     */
    protected function originEntry(): ?Entry
    {
        if ($this->isOriginLocale()) {
            return $this->editingId ? Entry::find($this->editingId) : null;
        }

        return $this->siblingEntryForSite($this->defaultSiteHandle());
    }

    /**
     * Overwrite non-localizable fields in $data with their value from
     * $originData in place — a non-localizable field only ever has one
     * value, always edited from the origin site, so any other site must
     * always show/save that same value instead of its own.
     */
    protected function overlayNonLocalizableData(array $fields, array &$data, array $originData): void
    {
        foreach ($fields as $field) {
            if ($field['type'] === 'replicator') {
                continue;
            }

            if (! ($field['localizable'] ?? false)) {
                $data[$field['handle']] = $originData[$field['handle']] ?? ($data[$field['handle']] ?? null);
            }
        }
    }

    /**
     * Starts a brand-new translation's localizable fields off with the
     * origin locale's value instead of blank — a translator begins from the
     * origin's content and only overrides what needs to differ, rather than
     * facing an empty field. Only for a not-yet-saved entry: once saved, a
     * locale's own (possibly still-untranslated) value is authoritative and
     * must not be silently overwritten by the origin's on every reopen.
     */
    protected function prefillLocalizableDataFromOrigin(array $fields, array &$data, array $originData): void
    {
        foreach ($fields as $field) {
            if ($field['type'] === 'replicator' || ! ($field['localizable'] ?? false)) {
                continue;
            }

            $data[$field['handle']] = $originData[$field['handle']] ?? $data[$field['handle']];
        }
    }

    /**
     * Every configured site, with an active flag and the URL the sidebar
     * site-switcher should link to: the sibling entry's edit page if this
     * entry (or its origin) already has one for that site, otherwise a
     * create page pre-linked to this entry via ?origin=, mirroring
     * Statamic's per-site entry localization.
     */
    #[Computed]
    public function siteLinks(): array
    {
        $configuredSites = $this->configuredSites();

        return Site::orderBy('order')
            ->when($configuredSites !== [], fn ($query) => $query->whereIn('handle', $configuredSites))
            ->get()
            ->filter(fn (Site $availableSite): bool => auth()->user()->canAccessSite($availableSite->handle))
            ->map(function (Site $availableSite): array {
                $active = $availableSite->handle === $this->site;

                if ($active) {
                    $href = null;
                } elseif ($sibling = $this->siblingEntryForSite($availableSite->handle)) {
                    $href = route('collections.entries.edit', [
                        'collection' => $this->collection->handle,
                        'entry' => $sibling->id,
                    ]);
                } else {
                    $href = route('collections.entries.create', [
                        'collection' => $this->collection->handle,
                        'site' => $availableSite->handle,
                        'blueprint' => $this->blueprintHandle,
                        'origin' => $this->originId ?? $this->editingId,
                    ]);
                }

                return [
                    'handle' => $availableSite->handle,
                    'name' => $availableSite->name,
                    'active' => $active,
                    'href' => $href,
                ];
            })
            ->all();
    }

    protected function siblingEntryForSite(string $siteHandle): ?Entry
    {
        $originId = $this->originId ?? $this->editingId;

        if (! $originId) {
            return null;
        }

        return Entry::where('collection', $this->collection->handle)->where('site', $siteHandle)->where(fn ($query) => $query->where('id', $originId)->orWhere('origin_id', $originId))->first();
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
     * Entries this one could be nested under, for a "Structured" collection
     * — every other entry in this collection on the current site.
     *
     * @return \Illuminate\Support\Collection<int, Entry>
     */
    #[Computed]
    public function availableParentEntries()
    {
        if (! $this->collection->structured()) {
            return collect();
        }

        return Entry::where('collection', $this->collection->handle)->where('site', $this->site)->when($this->editingId, fn ($query) => $query->where('id', '!=', $this->editingId))->orderBy('slug')->get();
    }

    protected function treeService(): CollectionTreeService
    {
        return app(CollectionTreeService::class);
    }

    /**
     * The public URL this entry can be visited at, per the collection's
     * route template for this site (see Collection::routeForSite()) — null
     * when the entry hasn't been saved yet, or the collection/site has no
     * route configured.
     */
    #[Computed]
    public function resolvedUrl(): ?string
    {
        if (! $this->editingId) {
            return null;
        }

        $entry = Entry::find($this->editingId);

        return $entry ? app(EntryUrlResolver::class)->url($this->collection, $entry) : null;
    }

    #[Computed]
    public function selectedBlueprint(): ?Blueprint
    {
        return $this->availableBlueprints->firstWhere('handle', $this->blueprintHandle);
    }

    #[Computed]
    public function blueprintFields(): array
    {
        return $this->expandFieldsetFields($this->selectedBlueprint?->data['fields'] ?? []);
    }

    /**
     * A "fieldset" field is a pure preset reference (see
     * App\Modules\Blueprints\Livewire\FieldsetManager) — it has no data of
     * its own, so it's replaced inline by the referenced Fieldset's own
     * fields (recursively, in case one of those is itself a "fieldset"),
     * inheriting the reference field's tab/section for grouping. This is
     * the single point every consumer of blueprintFields() (form rendering,
     * data seeding, validation rules, localization overlay) goes through,
     * so they never need to know "fieldset" exists.
     *
     * @param  array<int, string>  $seenFieldsetHandles  guards against a
     *                                                   fieldset (in)directly importing itself
     */
    protected function expandFieldsetFields(array $fields, array $seenFieldsetHandles = []): array
    {
        $expanded = [];

        foreach ($fields as $field) {
            if ($field['type'] === 'fieldset') {
                $handle = $field['options'] ?? null;

                if (! $handle || in_array($handle, $seenFieldsetHandles, true)) {
                    continue;
                }

                $subFields = Fieldset::where('handle', $handle)->first()?->data['fields'] ?? [];

                foreach ($this->expandFieldsetFields($subFields, [...$seenFieldsetHandles, $handle]) as $subField) {
                    $subField['tab'] = $field['tab'] ?? 'main';
                    $subField['section'] = $field['section'] ?? '';
                    $expanded[] = $subField;
                }

                continue;
            }

            if ($field['type'] === 'replicator') {
                $field['replicator_fields'] = $this->expandFieldsetFields($field['replicator_fields'] ?? [], $seenFieldsetHandles);
            }

            $expanded[] = $field;
        }

        return $expanded;
    }

    /**
     * Tabs the selected blueprint's top-level fields are grouped under —
     * see App\Modules\Blueprints\Livewire\BlueprintManager for how these
     * are defined. Falls back to a single "Main" tab for blueprints saved
     * before tabs existed.
     */
    #[Computed]
    public function blueprintTabs(): array
    {
        return $this->selectedBlueprint?->data['tabs'] ?? [['handle' => 'main', 'display' => 'Main']];
    }

    public function setActiveTab(string $handle): void
    {
        $this->activeTab = $handle;
    }

    protected function schemaLogoSentinel(): string
    {
        return '__entry_schema_logo__';
    }

    public function pickSeoImage(): void
    {
        $this->dispatch('open-asset-picker', path: ['__entry_seo_image__'], container: $this->assetContainer, selected: $this->seo['meta_featured_image'] ?? [], max: 1, validation: 'image');
    }

    public function removeSeoImage(int $assetId): void
    {
        $this->seo['meta_featured_image'] = collect($this->seo['meta_featured_image'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $assetId)
            ->values()
            ->all();
    }

    public function regenerateSlug(): void
    {
        $this->slug = Str::slug($this->title);
    }

    protected function defaultBlueprintHandle(): string
    {
        return $this->collection->effectiveBlueprint()?->handle ?? (string) $this->availableBlueprints->first()?->handle;
    }

    protected function fillData(?Entry $entry): void
    {
        $this->data = $this->fillFieldsData($this->blueprintFields(), $entry?->data);
    }

    /**
     * Recursively build a data array matching a field tree: a scalar value
     * (or its default) per field, or — for a replicator — one filled row
     * per existing entry, each row itself built the same way, to any depth.
     */
    protected function fillFieldsData(array $fields, ?array $existingData): array
    {
        $data = [];

        foreach ($fields as $field) {
            if ($field['type'] === 'replicator') {
                $existingRows = $existingData[$field['handle']] ?? [];
                $data[$field['handle']] = array_map(fn (array $row): array => array_merge(
                    ['_enabled' => $row['_enabled'] ?? true],
                    $this->fillFieldsData($field['replicator_fields'] ?? [], $row)
                ), $existingRows);

                continue;
            }

            if ($field['type'] === 'assets' || $field['type'] === 'taxonomy') {
                $data[$field['handle']] = $existingData[$field['handle']] ?? [];

                continue;
            }

            $data[$field['handle']] = $existingData[$field['handle']] ?? ($field['default'] ?? ($field['type'] === 'toggle' ? false : ''));
        }

        return $data;
    }

    /**
     * Append a new, default-filled row to the replicator field located at
     * $path — a list of field handles and row indexes describing where it
     * lives in $this->data, e.g. ["sections", 2, "items"] for the "items"
     * replicator inside row 2 of the top-level "sections" replicator.
     */
    public function addReplicatorRow(array $path): void
    {
        $field = $this->replicatorFieldAt($path);

        if (! $field) {
            return;
        }

        $rows = &$this->dataAt($path);

        if (($field['max_items'] ?? null) !== null && count($rows) >= $field['max_items']) {
            return;
        }

        $rows[] = array_merge(['_enabled' => true], $this->fillFieldsData($field['replicator_fields'] ?? [], null));
    }

    public function removeReplicatorRow(array $path, int $rowIndex): void
    {
        $rows = &$this->dataAt($path);
        unset($rows[$rowIndex]);
        $rows = array_values($rows);
    }

    /**
     * Inserts a copy of the row at $rowIndex right after it — same field
     * data (which itself gets its own fresh copy of whatever nested
     * replicator rows it has), so the duplicate is independently editable
     * from the moment it appears.
     */
    public function duplicateReplicatorRow(array $path, int $rowIndex): void
    {
        $rows = &$this->dataAt($path);

        if (! array_key_exists($rowIndex, $rows)) {
            return;
        }

        $field = $this->replicatorFieldAt($path);

        if (($field['max_items'] ?? null) !== null && count($rows) >= $field['max_items']) {
            return;
        }

        array_splice($rows, $rowIndex + 1, 0, [$rows[$rowIndex]]);
    }

    /**
     * Remove a single asset id from the "assets" field located at $path —
     * used by the "x" button on a selected asset's thumbnail.
     */
    public function removeAsset(array $path, int $assetId): void
    {
        $ids = &$this->dataAt($path);
        $ids = collect($ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->reject(fn (int $id): bool => $id === $assetId)
            ->values()
            ->all();
    }

    /**
     * Receives the result of the global asset picker modal (see
     * App\Modules\Media\Livewire\AssetPicker) and writes it into the
     * "assets" field it was opened for.
     *
     * @param  array<int, string|int>  $path
     * @param  array<int, int>  $ids
     */
    #[On('assets-selected')]
    public function assetsSelected(array $path, array $ids): void
    {
        // A "bard" field's image button opens the same shared picker with a
        // sentinel path (see entry-field.blade.php's bardEditor) since it
        // has no "assets" data property to write into — resolve URLs and
        // hand them back to the editor instead of touching $this->data.
        if (($path[0] ?? null) === '__bard__') {
            $this->dispatch('bard-image-selected', prefix: $path[1], mode: $path[2] ?? 'insert', items: AssetMeta::whereIn('id', $ids)->get()->map(fn (AssetMeta $asset): array => ['url' => $asset->url, 'mime' => $asset->mime_type, 'filename' => $asset->title])->all());

            return;
        }

        if ($this->schemaAssetsSelected($path, $ids)) {
            return;
        }

        if (($path[0] ?? null) === '__entry_seo_image__') {
            $this->seo['meta_featured_image'] = array_values($ids);

            return;
        }

        $ref = &$this->dataAt($path);
        $ref = array_values($ids);
    }

    /**
     * Entries a "link" field may point to — every entry on this site,
     * scoped to the field's allowed collections when configured.
     *
     * @param  array<int, string>  $allowedCollectionHandles
     * @return \Illuminate\Support\Collection<int, Entry>
     */
    public function linkableEntries(array $allowedCollectionHandles)
    {
        return Entry::where('site', $this->site)
            ->when($allowedCollectionHandles !== [], fn ($query) => $query->whereIn('collection', $allowedCollectionHandles))
            ->orderBy('collection')
            ->orderBy('slug')
            ->get();
    }

    /**
     * Users an "author" field may pick from.
     *
     * @return \Illuminate\Support\Collection<int, User>
     */
    public function availableAuthors()
    {
        return User::orderBy('name')->get();
    }

    /**
     * Terms a "taxonomy" field may pick from — every term of that taxonomy
     * on the entry's current site, since terms (like entries) are
     * per-site.
     *
     * @return \Illuminate\Support\Collection<int, TaxonomyTerm>
     */
    public function taxonomyTerms(?string $taxonomyHandle)
    {
        if (! $taxonomyHandle) {
            return collect();
        }

        return TaxonomyTerm::where('taxonomy', $taxonomyHandle)->where('site', $this->site)->orderBy('slug')->get();
    }

    /**
     * @return \Illuminate\Support\Collection<int, AssetMeta>
     */
    public function selectedAssets(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        $assets = AssetMeta::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $assets->get((int) $id))->filter()->values();
    }

    /**
     * The blueprint definition of the replicator field located at $path,
     * found by walking the field tree using only the handle segments (row
     * indexes don't affect which field definition applies).
     */
    protected function replicatorFieldAt(array $path): ?array
    {
        $fields = $this->blueprintFields();
        $field = null;

        foreach ($path as $segment) {
            if (is_int($segment)) {
                continue;
            }

            $field = collect($fields)->firstWhere('handle', $segment);

            if (! $field) {
                return null;
            }

            $fields = $field['replicator_fields'] ?? [];
        }

        return $field;
    }

    protected function &dataAt(array $path)
    {
        $ref = &$this->data;

        foreach ($path as $segment) {
            $ref = &$ref[$segment];
        }

        return $ref;
    }

    /**
     * Switching which blueprint an entry uses changes what fields exist,
     * so any values already typed for the old blueprint's fields no longer
     * make sense — reset to the new blueprint's defaults.
     */
    public function updated(string $name): void
    {
        if ($name === 'blueprintHandle') {
            $this->fillData(null);
            $this->activeTab = 'main';
        }

        if ($this->collection->livePreview()) {
            $this->refreshPreviewCache();
        }
    }

    /**
     * Build Laravel validation rules per blueprint field, using the field's
     * type, required flag, "sometimes" flag and custom rule string. A
     * replicator recurses into its own sub-fields under a "*" wildcard per
     * row, to any nesting depth. A top-level field hidden by its own
     * "show when"/"hide when" condition (see EvaluatesFieldConditions) is
     * relaxed to "nullable" instead of enforcing its normal rules, since the
     * user never had a chance to fill in a field they can't see. Conditions
     * on a replicator's own sub-fields aren't accounted for here — each row
     * can be independently hidden/shown, but a single wildcard rule applies
     * to every row alike, so those fields keep their normal validation
     * regardless of visibility.
     */
    protected function fieldRules(): array
    {
        return $this->fieldRulesFor($this->blueprintFields(), 'data', $this->data);
    }

    /**
     * @return array<string, mixed>
     */
    protected function seoValidationRules(): array
    {
        return [
            'seo.meta_title' => ['required', 'string', 'max:60'],
            'seo.meta_description' => ['required', 'string', 'max:160'],
            'seo.meta_canonical' => ['nullable', 'string', 'max:2048'],
            'seo.robots' => ['required', 'array', 'min:1'],
            'seo.robots.*' => [Rule::in(array_keys(self::SEO_ROBOTS_OPTIONS))],
            'seo.meta_featured_image' => ['required', 'array', 'min:1', 'max:1'],
            'seo.meta_featured_image.*' => ['integer', Rule::exists('assets_meta', 'id')],
            'seo.meta_author' => ['required', Rule::exists('users', 'id')],
            'seo.sitemap_priority' => ['required', Rule::in(self::SEO_SITEMAP_PRIORITIES)],
            'seo.sitemap_changefreq' => ['required', Rule::in(self::SEO_SITEMAP_CHANGEFREQS)],
        ];
    }

    /**
     * Maps each "data.*" validation key to its field's own display label
     * (e.g. "data.halo" => "Halo"), so a validation message reads "The
     * Halo field is required." instead of "The data.halo field is
     * required." — Livewire picks this up automatically (see
     * HandlesValidation::getValidationAttributes()).
     */
    public function validationAttributes(): array
    {
        return array_merge($this->fieldAttributesFor($this->blueprintFields(), 'data'), [
            'seo.meta_title' => __('Meta Title'),
            'seo.meta_description' => __('Meta Description'),
            'seo.meta_canonical' => __('Meta Canonical'),
            'seo.robots' => __('Robots'),
            'seo.meta_featured_image' => __('Meta Featured Image'),
            'seo.meta_author' => __('Meta Author'),
            'seo.sitemap_priority' => __('Sitemap Priority'),
            'seo.sitemap_changefreq' => __('Sitemap Changefreq'),
        ]);
    }

    protected function fieldAttributesFor(array $fields, string $prefix): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            $key = "{$prefix}.{$field['handle']}";
            $attributes[$key] = $field['display'] ?? $field['handle'];

            if ($field['type'] === 'replicator') {
                $attributes = array_merge($attributes, $this->fieldAttributesFor($field['replicator_fields'] ?? [], "{$key}.*"));
            }
        }

        return $attributes;
    }

    protected function fieldRulesFor(array $fields, string $prefix, ?array $scopeValues = null): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = "{$prefix}.{$field['handle']}";

            if ($field['type'] === 'replicator') {
                $rules[$key] = ['array'];

                if ($field['required'] ?? false) {
                    $rules[$key][] = 'required';
                }

                if (($field['max_items'] ?? null) !== null) {
                    $rules[$key][] = "max:{$field['max_items']}";
                }

                $rules["{$key}.*._enabled"] = ['boolean'];
                $rules = array_merge($rules, $this->fieldRulesFor($field['replicator_fields'] ?? [], "{$key}.*"));

                continue;
            }

            if ($scopeValues !== null && ! $this->fieldIsVisible($field, $scopeValues)) {
                $rules[$key] = ['nullable'];

                continue;
            }

            // A non-localizable field isn't editable outside the origin
            // locale — the form shows it read-only, and overlayNonLocalizableData()
            // overwrites whatever was submitted with the origin entry's value
            // right after validation. Enforcing the field's own rules (e.g.
            // "required") here would block saving this translation over a
            // value the user can't even see or change from this page.
            if (! ($field['localizable'] ?? false) && ! $this->isOriginLocale()) {
                $rules[$key] = ['nullable'];

                continue;
            }

            if ($field['type'] === 'assets') {
                $arrayRules = [];

                if ($field['validate_sometimes'] ?? false) {
                    $arrayRules[] = 'sometimes';
                }

                $arrayRules[] = $field['required'] ?? false ? 'required' : 'nullable';
                $arrayRules[] = 'array';

                if (($field['min_items'] ?? null) !== null) {
                    $arrayRules[] = "min:{$field['min_items']}";
                }

                if (($field['max_items'] ?? null) !== null) {
                    $arrayRules[] = "max:{$field['max_items']}";
                }

                $rules[$key] = $arrayRules;
                $rules["{$key}.*"] = ['integer', Rule::exists('assets_meta', 'id')->where('container', $field['options'] ?? null)];

                if (! empty($field['validation'])) {
                    $rules["{$key}.*"][] = function (string $attribute, mixed $value, \Closure $fail) use ($field): void {
                        $asset = AssetMeta::find($value);

                        if (! $asset) {
                            return;
                        }

                        $file = new File($asset->absolutePath());

                        if (Validator::make(['file' => $file], ['file' => $field['validation']])->fails()) {
                            $fail(__('The selected file does not match the allowed file type.'));
                        }
                    };
                }

                continue;
            }

            if ($field['type'] === 'taxonomy') {
                $arrayRules = [];

                if ($field['validate_sometimes'] ?? false) {
                    $arrayRules[] = 'sometimes';
                }

                $arrayRules[] = $field['required'] ?? false ? 'required' : 'nullable';
                $arrayRules[] = 'array';

                if (($field['min_items'] ?? null) !== null) {
                    $arrayRules[] = "min:{$field['min_items']}";
                }

                if (($field['max_items'] ?? null) !== null) {
                    $arrayRules[] = "max:{$field['max_items']}";
                }

                $rules[$key] = $arrayRules;
                $rules["{$key}.*"] = [
                    'integer',
                    Rule::exists('taxonomy_terms', 'id')
                        ->where('taxonomy', $field['options'] ?? null)
                        ->where('site', $this->site),
                ];

                continue;
            }

            if ($field['type'] === 'checkbox') {
                $arrayRules = [];

                if ($field['validate_sometimes'] ?? false) {
                    $arrayRules[] = 'sometimes';
                }

                $arrayRules[] = $field['required'] ?? false ? 'required' : 'nullable';
                $arrayRules[] = 'array';

                if (($field['min_items'] ?? null) !== null) {
                    $arrayRules[] = "min:{$field['min_items']}";
                }

                if (($field['max_items'] ?? null) !== null) {
                    $arrayRules[] = "max:{$field['max_items']}";
                }

                $rules[$key] = $arrayRules;
                $rules["{$key}.*"] = [Rule::in($field['options'] ?? [])];

                continue;
            }

            if ($field['type'] === 'link') {
                $linkRules = [];

                if ($field['validate_sometimes'] ?? false) {
                    $linkRules[] = 'sometimes';
                }

                $linkRules[] = $field['required'] ?? false ? 'required' : 'nullable';
                $linkRules[] = 'string';
                $linkRules[] = 'max:2048';

                $allowedCollections = $field['options'] ?? [];

                $linkRules[] = function (string $attribute, mixed $value, \Closure $fail) use ($allowedCollections): void {
                    if (! is_string($value) || ! str_starts_with($value, 'entry::')) {
                        return;
                    }

                    $linkedEntry = Entry::find(substr($value, 7));

                    if (! $linkedEntry) {
                        $fail(__('The linked entry no longer exists.'));
                    } elseif (! empty($allowedCollections) && ! in_array($linkedEntry->collection, $allowedCollections, true)) {
                        $fail(__('The linked entry is not in an allowed collection.'));
                    }
                };

                if (! empty($field['validation'])) {
                    $linkRules[] = $field['validation'];
                }

                $rules[$key] = $linkRules;

                continue;
            }

            $fieldRules = [];

            if ($field['validate_sometimes'] ?? false) {
                $fieldRules[] = 'sometimes';
            }

            $fieldRules[] = $field['required'] ?? false ? 'required' : 'nullable';

            $fieldRules[] = match ($field['type']) {
                'number' => 'numeric',
                'toggle' => 'boolean',
                'date' => 'date',
                'select', 'radio' => Rule::in($field['options'] ?? []),
                'author' => Rule::exists('users', 'id'),
                default => 'string',
            };

            if (! empty($field['validation'])) {
                $fieldRules[] = $field['validation'];
            }

            $rules[$key] = $fieldRules;
        }

        return $rules;
    }

    public function save(): void
    {
        if ($this->order === '') {
            $this->order = null;
        }

        if ($this->parentId === '') {
            $this->parentId = null;
        }

        $rules = array_merge(
            [
                'title' => ['required', 'string', 'max:300'],
                'slug' => ['required', 'string', 'max:255'],
                'status' => ['required', Rule::in(array_keys(self::STATUSES))],
                'entryDate' => ['required', 'date'],
                'order' => ['nullable', 'integer'],
                'blueprintHandle' => ['required', Rule::in($this->availableBlueprints->pluck('handle')->all())],
            ],
            $this->fieldRules(),
        );

        if ($this->collection->schemaEnabled()) {
            $rules = array_merge($rules, $this->schemaBlockValidationRules());
        }

        if ($this->collection->seoEnabled()) {
            $rules = array_merge($rules, $this->seoValidationRules());
        }

        $validated = $this->validate($rules);

        // Whether a "Published"/"Scheduled" pick actually goes live now or
        // later is decided by the date, not by which of the two was picked
        // — a future date always stores "scheduled", mirroring Statamic.
        // The entries:publish-scheduled scheduled command flips it to
        // "published" once that date passes (see routes/console.php).
        if (in_array($validated['status'], ['published', 'scheduled'], true)) {
            $validated['status'] = Carbon::parse($validated['entryDate'])->isFuture() ? 'scheduled' : 'published';
        }

        // The status picker is disabled for anyone who can't manage this
        // entry's publish state (see canEditStatusOf()): changing it in ANY
        // direction — into published/scheduled or between draft/need_review —
        // needs the collection's "publish {handle} entries" permission, and
        // editing someone else's entry additionally "publish other authors
        // {handle} entries". Saving with the status unchanged never needs a
        // publish right (a reviewer editing content keeps editing without one).
        $entry = $this->entry();

        if ($validated['status'] !== ($entry?->status ?? 'draft') && ! $this->canEditStatusOf($entry)) {
            abort(403);
        }

        $data = $validated['data'] ?? [];
        $data['title'] = $validated['title'];

        if (! $this->isOriginLocale()) {
            $originData = $this->fillFieldsData($this->blueprintFields(), $this->originEntry()?->data);
            $this->overlayNonLocalizableData($this->blueprintFields(), $data, $originData);

            if ($this->collection->seoEnabled()) {
                $originSeo = $this->originEntry()?->seoFields() ?? Entry::defaultSeoFields();

                foreach (self::SEO_LOCALIZABLE as $key => $localizable) {
                    if (! $localizable) {
                        $validated['seo'][$key] = $originSeo[$key];
                    }
                }
            }
        }

        // A structured+orderable collection's first root entry for a site
        // becomes that site's homepage only via order = 1 (see
        // EntryUrlResolver::path()) — without this, a freshly-added
        // translation stays orderless and never becomes its site's
        // homepage until someone drags it or types "1" in manually.
        $order = $validated['order'] !== null ? (int) $validated['order'] : null;

        if ($order === null && ! $this->editingId && $this->collection->structured() && $this->collection->orderable() && $this->parentId === null) {
            $hasHomepage = Entry::where('collection', $this->collection->handle)
                ->where('site', $this->site)
                ->where('order', 1)
                ->exists();

            if (! $hasHomepage) {
                $order = 1;
            }
        }

        $attributes = [
            'site' => $this->site,
            'origin_id' => $this->originId,
            'collection' => $this->collection->handle,
            'blueprint' => $validated['blueprintHandle'],
            'slug' => $validated['slug'],
            'status' => $validated['status'],
            'published' => $validated['status'] === 'published',
            'date' => str_replace('T', ' ', $validated['entryDate']),
            'order' => $order,
            'data' => $data,
        ];

        if ($this->collection->schemaEnabled()) {
            $attributes['schema'] = $this->cleanedSchemaBlocks($validated['schemaBlocks'] ?? []);
        }

        if ($this->collection->seoEnabled()) {
            $attributes['seo'] = $validated['seo'] ?? [];
        }

        $entry = Entry::updateOrCreate(['id' => $this->editingId], $attributes);

        if ($this->collection->structured()) {
            try {
                $this->treeService()->setParent($this->collection->handle, $this->site, $entry->id, $this->parentId, $this->collection->maxDepth());
            } catch (InvalidArgumentException $e) {
                $this->addError('parentId', $e->getMessage());

                return;
            }
        }

        $entry->update(['uri' => app(EntryUrlResolver::class)->path($this->collection, $entry)]);

        Flux::toast(variant: 'success', text: __('Entry saved.'));

        match ($this->collection->saveAction()) {
            'editing' => $this->redirectRoute(
                'collections.entries.edit',
                [
                    'collection' => $this->collection->handle,
                    'entry' => $entry->id,
                ],
                navigate: true,
            ),
            'create_another' => $this->redirectRoute(
                'collections.entries.create',
                [
                    'collection' => $this->collection->handle,
                    'site' => $this->site,
                    'blueprint' => $this->blueprintHandle,
                ],
                navigate: true,
            ),
            default => $this->redirectRoute('collections.entries', ['collection' => $this->collection->handle], navigate: true),
        };
    }

    public function render(): View
    {
        return view('collections::livewire.entry-form', [
            'statuses' => self::STATUSES,
            'canEditStatus' => $this->canEditStatus,
        ])->title($this->editingId ? __('Edit entry') : __('New entry'));
    }

    /**
     * Whether the status picker is enabled for the entry being edited —
     * see canEditStatusOf().
     */
    #[Computed]
    public function canEditStatus(): bool
    {
        return $this->canEditStatusOf($this->entry());
    }

    /**
     * Whether the user may change the status picker for the given entry.
     * Moving the workflow state in any direction (Published / Scheduled /
     * Need Review / Draft) requires the collection's base "publish {handle}
     * entries" permission; editing someone else's entry (decided by the SEO
     * Meta Author field, see Entry::ownedBy()) additionally requires
     * "publish other authors {handle} entries".
     */
    protected function canEditStatusOf(?Entry $entry): bool
    {
        $user = auth()->user();

        if (! $user->hasPermission("publish {$this->collection->handle} entries")) {
            return false;
        }

        return $entry === null || $entry->ownedBy($user)
            || $user->hasPermission("publish other authors {$this->collection->handle} entries");
    }
}
