<?php

namespace App\Modules\Taxonomies\Livewire;

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Support\EvaluatesFieldConditions;
use App\Modules\Collections\Models\Entry;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Sites\Models\Site;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Full-page create/edit form for a taxonomy term — mirrors
 * App\Modules\Collections\Livewire\EntryForm (field/replicator/assets/link
 * scaffolding, origin-site overlay for non-localizable fields), but the
 * site switcher is scoped to the taxonomy's own configured sites
 * (settings.sites), the same convention GlobalSetManager/GlobalSetForm use.
 */
class TermForm extends Component
{
    use EvaluatesFieldConditions;

    const DEFAULT_SITE = 'indonesia';

    public Taxonomy $taxonomy;

    public ?int $editingId = null;

    public string $site = self::DEFAULT_SITE;

    public string $title = '';

    public string $slug = '';

    public string $blueprintHandle = '';

    /**
     * Which of the selected blueprint's tabs is currently shown — see
     * blueprintTabs().
     */
    public string $activeTab = 'main';

    /**
     * The term this one is a localization of — self-referencing, so
     * switching sites can find (or create) the sibling term for that site.
     */
    public ?int $originId = null;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(Taxonomy $taxonomy, ?TaxonomyTerm $term = null, ?string $site = null): void
    {
        auth()->user()->authorizePermission($term ? "edit {$taxonomy->handle} terms" : "create {$taxonomy->handle} terms");

        $this->taxonomy = $taxonomy;

        if ($term) {
            if ($term->taxonomy !== $taxonomy->handle) {
                throw new NotFoundHttpException;
            }

            $this->editingId = $term->id;
            $this->site = $term->site;
            $this->title = (string) ($term->data['title'] ?? '');
            $this->slug = (string) $term->slug;
            $this->blueprintHandle = $term->blueprint ?? $this->defaultBlueprintHandle();
            $this->originId = $term->origin_id;
        } else {
            $this->site = $site ?: ($this->defaultSiteHandle() ?? self::DEFAULT_SITE);
            $this->blueprintHandle = request()->string('blueprint')->toString() ?: $this->defaultBlueprintHandle();
            $this->originId = request()->integer('origin') ?: null;
        }

        abort_unless(auth()->user()->canAccessSite($this->site), 403);

        $this->fillData($term);

        if (! $this->isOriginLocale()) {
            $originData = $this->fillFieldsData($this->blueprintFields(), $this->originTerm()?->data);
            $this->overlayNonLocalizableData($this->blueprintFields(), $this->data, $originData);

            // A link pointing at the default language's term/entry shows
            // that same content's version on this site, so the picker is
            // pre-selected on the translation instead of the first option.
            $this->data = Entry::localizeLinkValues($this->data, $this->blueprintFields(), $this->site);
        }
    }

    /**
     * The taxonomy's configured sites — mirrors GlobalSetManager::$sites.
     * Empty means every site is available.
     *
     * @return array<int, string>
     */
    protected function configuredSites(): array
    {
        return $this->taxonomy->sites ?? [];
    }

    public function regenerateSlug(): void
    {
        $this->slug = Str::slug($this->title);
    }

    /**
     * The site treated as this taxonomy's default/origin language — the
     * first of its configured sites by order, or the first site overall
     * when none are configured. Same convention as
     * GlobalSetManager::defaultSiteHandle().
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
     * The sibling term (or this term itself) on the default/origin site —
     * authoritative source for non-localizable field values.
     */
    protected function originTerm(): ?TaxonomyTerm
    {
        if ($this->isOriginLocale()) {
            return $this->editingId ? TaxonomyTerm::find($this->editingId) : null;
        }

        return $this->siblingTermForSite($this->defaultSiteHandle());
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
                $data[$field['handle']] = $originData[$field['handle']] ?? $data[$field['handle']] ?? null;
            }
        }
    }

    /**
     * Site switcher links — only the taxonomy's configured sites (or every
     * site, if none are configured), same fallback GlobalSetForm uses.
     *
     * @return array<int, array{handle: string, name: string, active: bool, href: ?string}>
     */
    #[Computed]
    public function siteLinks(): array
    {
        $configuredSites = $this->configuredSites();

        $sites = Site::orderBy('order')
            ->when($configuredSites !== [], fn ($query) => $query->whereIn('handle', $configuredSites))
            ->get()
            ->filter(fn (Site $site): bool => auth()->user()->canAccessSite($site->handle))
            ->values();

        if ($sites->count() <= 1) {
            return [];
        }

        return $sites
            ->map(function (Site $availableSite): array {
                $active = $availableSite->handle === $this->site;

                if ($active) {
                    $href = null;
                } elseif ($sibling = $this->siblingTermForSite($availableSite->handle)) {
                    $href = route('taxonomies.terms.edit', [
                        'taxonomy' => $this->taxonomy->handle,
                        'term' => $sibling->id,
                    ]);
                } else {
                    $href = route('taxonomies.terms.create', [
                        'taxonomy' => $this->taxonomy->handle,
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

    protected function siblingTermForSite(?string $siteHandle): ?TaxonomyTerm
    {
        $originId = $this->originId ?? $this->editingId;

        if (! $originId) {
            return null;
        }

        return TaxonomyTerm::where('taxonomy', $this->taxonomy->handle)
            ->where('site', $siteHandle)
            ->where(fn ($query) => $query->where('id', $originId)->orWhere('origin_id', $originId))
            ->first();
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

    #[Computed]
    public function selectedBlueprint(): ?Blueprint
    {
        return $this->availableBlueprints->firstWhere('handle', $this->blueprintHandle);
    }

    #[Computed]
    public function blueprintFields(): array
    {
        return $this->selectedBlueprint?->data['fields'] ?? [];
    }

    /**
     * Tabs the selected blueprint's top-level fields are grouped under.
     * Falls back to a single "Main" tab for blueprints saved before tabs
     * existed.
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

    protected function defaultBlueprintHandle(): string
    {
        return $this->taxonomy->effectiveBlueprint()?->handle
            ?? (string) $this->availableBlueprints->first()?->handle;
    }

    protected function fillData(?TaxonomyTerm $term): void
    {
        $this->data = $this->fillFieldsData($this->blueprintFields(), $term?->data);
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
                $data[$field['handle']] = array_map(
                    fn (array $row): array => array_merge(
                        ['_enabled' => $row['_enabled'] ?? true],
                        $this->fillFieldsData($field['replicator_fields'] ?? [], $row)
                    ),
                    $existingRows
                );

                continue;
            }

            if ($field['type'] === 'assets') {
                $data[$field['handle']] = $existingData[$field['handle']] ?? [];

                continue;
            }

            $data[$field['handle']] = $existingData[$field['handle']]
                ?? $field['default']
                ?? ($field['type'] === 'toggle' ? false : '');
        }

        return $data;
    }

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

    public function removeAsset(array $path, int $assetId): void
    {
        $ids = &$this->dataAt($path);
        $ids = collect($ids ?? [])->map(fn ($id): int => (int) $id)->reject(fn (int $id): bool => $id === $assetId)->values()->all();
    }

    /**
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

        $ref = &$this->dataAt($path);
        $ref = array_values($ids);
    }

    /**
     * Entries a "link" field may point to — reuses the Collections entry
     * pool, same as EntryForm/GlobalSetForm.
     *
     * @return Collection<int, Entry>
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
     * @return Collection<int, User>
     */
    public function availableAuthors()
    {
        return User::orderBy('name')->get();
    }

    /**
     * @return Collection<int, AssetMeta>
     */
    public function selectedAssets(array $ids)
    {
        if (empty($ids)) {
            return collect();
        }

        $assets = AssetMeta::whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $assets->get((int) $id))->filter()->values();
    }

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
     * Switching which blueprint a term uses changes what fields exist, so
     * any values already typed for the old blueprint's fields no longer
     * make sense — reset to the new blueprint's defaults.
     */
    public function updated(string $name): void
    {
        if ($name === 'blueprintHandle') {
            $this->fillData(null);
            $this->activeTab = 'main';
        }
    }

    protected function fieldRules(): array
    {
        return $this->fieldRulesFor($this->blueprintFields(), 'data', $this->data);
    }

    /**
     * Maps each "data.*" validation key to its field's own display label,
     * so a validation message reads "The Halo field is required." instead
     * of "The data.halo field is required."
     */
    public function validationAttributes(): array
    {
        return $this->fieldAttributesFor($this->blueprintFields(), 'data');
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

            if ($field['type'] === 'assets') {
                $arrayRules = [];

                if ($field['validate_sometimes'] ?? false) {
                    $arrayRules[] = 'sometimes';
                }

                $arrayRules[] = ($field['required'] ?? false) ? 'required' : 'nullable';
                $arrayRules[] = 'array';

                if (($field['min_items'] ?? null) !== null) {
                    $arrayRules[] = "min:{$field['min_items']}";
                }

                if (($field['max_items'] ?? null) !== null) {
                    $arrayRules[] = "max:{$field['max_items']}";
                }

                $rules[$key] = $arrayRules;
                $rules["{$key}.*"] = ['integer', Rule::exists('assets_meta', 'id')->where('container', $field['options'] ?? null)];

                continue;
            }

            if ($field['type'] === 'checkbox') {
                $arrayRules = [];

                if ($field['validate_sometimes'] ?? false) {
                    $arrayRules[] = 'sometimes';
                }

                $arrayRules[] = ($field['required'] ?? false) ? 'required' : 'nullable';
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

                $linkRules[] = ($field['required'] ?? false) ? 'required' : 'nullable';
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

            $fieldRules[] = ($field['required'] ?? false) ? 'required' : 'nullable';

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
        $validated = $this->validate(array_merge([
            'title' => ['required', 'string', 'max:300'],
            'slug' => ['required', 'string', 'max:255'],
            'blueprintHandle' => ['required', Rule::in($this->availableBlueprints->pluck('handle')->all())],
        ], $this->fieldRules()));

        $data = $validated['data'] ?? [];
        $data['title'] = $validated['title'];

        if (! $this->isOriginLocale()) {
            $originData = $this->fillFieldsData($this->blueprintFields(), $this->originTerm()?->data);
            $this->overlayNonLocalizableData($this->blueprintFields(), $data, $originData);
        }

        $term = TaxonomyTerm::updateOrCreate(['id' => $this->editingId], [
            'site' => $this->site,
            'origin_id' => $this->originId,
            'taxonomy' => $this->taxonomy->handle,
            'blueprint' => $validated['blueprintHandle'],
            'slug' => $validated['slug'],
            'data' => $data,
        ]);

        Flux::toast(variant: 'success', text: __('Term saved.'));

        match ($this->taxonomy->saveAction()) {
            'editing' => $this->redirectRoute('taxonomies.terms.edit', [
                'taxonomy' => $this->taxonomy->handle,
                'term' => $term->id,
            ], navigate: true),
            'create_another' => $this->redirectRoute('taxonomies.terms.create', [
                'taxonomy' => $this->taxonomy->handle,
                'site' => $this->site,
                'blueprint' => $this->blueprintHandle,
            ], navigate: true),
            default => $this->redirectRoute('taxonomies.terms', ['taxonomy' => $this->taxonomy->handle], navigate: true),
        };
    }

    public function render(): View
    {
        return view('taxonomies::livewire.term-form')
            ->title($this->editingId ? __('Edit term') : __('New term'));
    }
}
