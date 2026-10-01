<?php

namespace App\Modules\Globals\Livewire;

use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Modules\Blueprints\Models\Fieldset;
use App\Modules\Blueprints\Support\EvaluatesFieldConditions;
use App\Modules\Collections\Models\Entry;
use App\Modules\Globals\Models\GlobalSet;
use App\Modules\Globals\Models\GlobalSetVariable;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Sites\Models\Site;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Editor for a single global set's values, rendered from its blueprint's
 * fields — mirrors App\Modules\Collections\Livewire\EntryForm, reusing its
 * field-rendering partial (collections::livewire.partials.entry-field) so
 * every blueprint field type (including replicator/assets/link) works here
 * too without duplicating that view.
 */
class GlobalSetForm extends Component
{
    use EvaluatesFieldConditions;

    public GlobalSet $globalSet;

    public string $activeTab = 'main';

    /**
     * The site whose values are being edited, synced with ?site=... in the
     * URL. Only meaningful when the global set supports more than one site
     * (see GlobalSetManager::$sites) — otherwise values are stored under a
     * null locale and this stays empty.
     */
    #[Url(as: 'site')]
    public string $activeSite = '';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(GlobalSet $globalSet): void
    {
        auth()->user()->authorizePermission("view {$globalSet->handle} globals");

        $this->globalSet = $globalSet;

        $configuredSites = $globalSet->settings['sites'] ?? [];

        if ($configuredSites !== []) {
            $firstAccessible = collect($configuredSites)
                ->first(fn (string $handle): bool => auth()->user()->canAccessSite($handle));

            abort_unless($firstAccessible, 403);

            if (! in_array($this->activeSite, $configuredSites, true) || ! auth()->user()->canAccessSite($this->activeSite)) {
                $this->activeSite = $firstAccessible;
            }
        }

        $variable = GlobalSetVariable::firstOrCreate(
            ['handle' => $globalSet->handle, 'locale' => $this->activeLocale()],
            ['data' => []]
        );

        $this->data = $this->fillFieldsData($this->blueprintFields(), $variable->data);

        if (! $this->isOriginLocale()) {
            $originVariable = GlobalSetVariable::firstOrCreate(
                ['handle' => $globalSet->handle, 'locale' => $this->originLocale()],
                ['data' => []]
            );
            $originData = $this->fillFieldsData($this->blueprintFields(), $originVariable->data);

            $this->overlayNonLocalizableData($this->blueprintFields(), $this->data, $originData);

            // A link pointing at the default language's entry shows
            // that same content's entry on this site, so the picker is
            // pre-selected on the translation instead of the first
            // option.
            $this->data = Entry::localizeLinkValues($this->data, $this->blueprintFields(), $this->activeSite);
        }
    }

    protected function activeLocale(): ?string
    {
        return $this->activeSite !== '' ? $this->activeSite : null;
    }

    /**
     * The site whose value is authoritative for non-localizable fields —
     * the same "default/origin" site GlobalSetManager forces into every
     * global set's Languages list. Single-language global sets (no sites
     * configured) have no site at all, so their one row (locale = null) is
     * always the origin.
     */
    protected function originLocale(): ?string
    {
        $configuredSites = $this->globalSet->settings['sites'] ?? [];

        if ($configuredSites === []) {
            return null;
        }

        return Site::orderBy('order')->value('handle');
    }

    public function isOriginLocale(): bool
    {
        return $this->activeLocale() === $this->originLocale();
    }

    /**
     * Overwrite non-localizable fields in $data with their value from
     * $originData in place — a non-localizable field only ever has one
     * value, always edited from the origin locale, so any other locale must
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
     * Site switcher links for the values page sidebar — only shown when the
     * global set is configured for more than one site (see
     * GlobalSetManager's "Languages" checkbox group). Same route as this
     * page, just a different ?site=... query string.
     *
     * @return array<int, array{handle: string, name: string, active: bool, href: string}>
     */
    #[Computed]
    public function siteLinks(): array
    {
        $configuredSites = $this->globalSet->settings['sites'] ?? [];

        if (count($configuredSites) <= 1) {
            return [];
        }

        return Site::whereIn('handle', $configuredSites)
            ->orderBy('order')
            ->get()
            ->filter(fn (Site $site): bool => auth()->user()->canAccessSite($site->handle))
            ->map(fn (Site $site): array => [
                'handle' => $site->handle,
                'name' => $site->name,
                'active' => $site->handle === $this->activeLocale(),
                'href' => route('globals.edit', ['globalSet' => $this->globalSet->handle, 'site' => $site->handle]),
            ])
            ->all();
    }

    #[Computed]
    public function blueprintFields(): array
    {
        return $this->expandFieldsetFields($this->globalSet->effectiveBlueprint()?->data['fields'] ?? []);
    }

    /**
     * A "fieldset" field is a pure preset reference (see
     * App\Modules\Blueprints\Livewire\FieldsetManager) — it has no data of
     * its own, so it's replaced inline by the referenced Fieldset's own
     * fields (recursively, in case one of those is itself a "fieldset"),
     * inheriting the reference field's tab/section for grouping. Mirrors
     * EntryForm::expandFieldsetFields(), and every consumer of
     * blueprintFields() (form rendering, data seeding, validation rules,
     * localization overlay) goes through it so none need to know
     * "fieldset" exists.
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

    #[Computed]
    public function blueprintTabs(): array
    {
        return $this->globalSet->effectiveBlueprint()?->data['tabs'] ?? [['handle' => 'main', 'display' => 'Main']];
    }

    public function setActiveTab(string $handle): void
    {
        $this->activeTab = $handle;
    }

    /**
     * Recursively build a data array matching a field tree — see
     * EntryForm::fillFieldsData(), which this mirrors.
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

            if ($field['type'] === 'assets' || $field['type'] === 'taxonomy') {
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
     * @return Collection<int, Entry>
     */
    public function linkableEntries(array $allowedCollectionHandles)
    {
        return Entry::when($allowedCollectionHandles !== [], fn ($query) => $query->whereIn('collection', $allowedCollectionHandles))
            // ponytail: single-site globals (activeSite = "") list all sites'
            // entries unscoped; scope only once the set is site-localized.
            ->when($this->activeSite !== '', fn ($query) => $query->where('site', $this->activeSite))
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
     * Terms a "taxonomy" field may pick from — every term of that taxonomy,
     * scoped to the active site when the global set is site-localized.
     * Mirrors EntryForm::taxonomyTerms().
     *
     * @return Collection<int, TaxonomyTerm>
     */
    public function taxonomyTerms(?string $taxonomyHandle)
    {
        if (! $taxonomyHandle) {
            return collect();
        }

        // ponytail: single-site globals (activeSite = "") list all sites'
        // terms unscoped; scope only once the set is site-localized.
        return TaxonomyTerm::where('taxonomy', $taxonomyHandle)
            ->when($this->activeSite !== '', fn ($query) => $query->where('site', $this->activeSite))
            ->orderBy('slug')
            ->get();
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

            if ($field['type'] === 'taxonomy') {
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

                $exists = Rule::exists('taxonomy_terms', 'id')->where('taxonomy', $field['options'] ?? null);

                if ($this->activeSite !== '') {
                    $exists = $exists->where('site', $this->activeSite);
                }

                $rules["{$key}.*"] = ['integer', $exists];

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
        auth()->user()->authorizePermission("edit {$this->globalSet->handle} globals");

        $validated = $this->validate($this->fieldRules());
        $data = $validated['data'] ?? [];

        if (! $this->isOriginLocale()) {
            $originVariable = GlobalSetVariable::firstOrCreate(
                ['handle' => $this->globalSet->handle, 'locale' => $this->originLocale()],
                ['data' => []]
            );

            $this->overlayNonLocalizableData($this->blueprintFields(), $data, $originVariable->data);
        }

        GlobalSetVariable::updateOrCreate(
            ['handle' => $this->globalSet->handle, 'locale' => $this->activeLocale()],
            ['data' => $data]
        );

        Flux::toast(variant: 'success', text: __('Global set saved.'));

        match ($this->globalSet->saveAction()) {
            'editing' => $this->redirectRoute('globals.edit', array_filter([
                'globalSet' => $this->globalSet->handle,
                'site' => $this->activeSite,
            ]), navigate: true),
            default => $this->redirectRoute('globals.index', navigate: true),
        };
    }

    public function render(): View
    {
        return view('globals::livewire.global-set-form');
    }
}
