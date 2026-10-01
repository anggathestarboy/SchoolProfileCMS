<?php

namespace App\Modules\Navigation\Livewire;

use App\Models\TaxonomyTerm;
use App\Modules\Blueprints\Support\EvaluatesFieldConditions;
use App\Modules\Collections\Models\Entry;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Navigation\Models\Navigation;
use App\Modules\Navigation\Services\NavigationTreeService;
use App\Modules\Sites\Models\Site;
use App\Modules\Users\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Edits a navigation's content. Two entirely different modes depending on
 * whether the navigation has a blueprint with fields:
 *
 * - Blueprint mode (the normal case): a single blueprint-driven form, just
 *   like GlobalSetForm/EntryForm/TermForm — no tree, no "Add item" button.
 *   Repeated menu items (e.g. links) are a "replicator" field the blueprint
 *   author defines themselves, with its own "Add row" button, mirroring
 *   Statamic's nav-via-fieldset concept.
 * - Legacy tree mode: the original free-form nested tree editor (add/move/
 *   duplicate/delete items, drag-and-drop). Only reachable for protected
 *   navigations with no blueprint, i.e. sidebar-admin — see
 *   NavigationManager, which disables "Edit tree" for anything else without
 *   blueprint fields.
 */
#[Title('Navigation')]
class NavigationTreeEditor extends Component
{
    use EvaluatesFieldConditions;

    public Navigation $navigation;

    /**
     * @var array<int, array>
     */
    public array $tree = [];

    /**
     * @var array<int, string>
     */
    public array $expanded = [];

    public ?string $deletingId = null;

    public ?string $movingId = null;

    public string $moveTargetParentId = '';

    public string $moveChildrenStrategy = '';

    public string $activeTab = 'main';

    /**
     * The site whose values are being edited, synced with ?site=... in the
     * URL — mirrors GlobalSetForm::$activeSite. Only meaningful in blueprint
     * mode when more than one site is configured; otherwise the tree is
     * stored under a null locale and this stays empty.
     */
    #[Url(as: 'site')]
    public string $activeSite = '';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(Navigation $navigation): void
    {
        auth()->user()->authorizePermission("view {$navigation->handle} navigation");

        $this->navigation = $navigation;

        if ($this->usesBlueprint()) {
            if ($this->availableSites->count() > 1 && ! $this->availableSites->pluck('handle')->contains($this->activeSite)) {
                $this->activeSite = (string) $this->originLocale();
            }

            $this->loadBlueprintData();

            return;
        }

        $this->tree = $this->treeService()->load($navigation);
    }

    public function updatedActiveSite(): void
    {
        if ($this->usesBlueprint()) {
            $this->loadBlueprintData();
        }
    }

    /**
     * A non-origin locale's stored row only ever holds the fields it has
     * been explicitly given their own value for (see save()) — anything
     * else is missing on purpose, so it keeps inheriting whatever the
     * default language currently has. Merging origin's full data underneath
     * the locale's own (partial) row gives every field a value while
     * letting untouched fields keep following the default language.
     */
    protected function loadBlueprintData(): void
    {
        $ownData = $this->treeService()->load($this->navigation, $this->activeLocale());

        if ($this->isOriginLocale()) {
            $this->data = $this->fillFieldsData($this->blueprintFields(), $ownData);

            return;
        }

        $originData = $this->treeService()->load($this->navigation, $this->originLocale());
        $this->data = $this->fillFieldsData($this->blueprintFields(), array_merge($originData, $ownData));

        // A link pointing at the default language's entry shows that same
        // content's entry on this site, so the picker is pre-selected on
        // the translation instead of the first option.
        $this->data = Entry::localizeLinkValues($this->data, $this->blueprintFields(), $this->activeSite);
    }

    #[Computed]
    public function availableSites(): Collection
    {
        return Site::orderBy('order')->get();
    }

    protected function activeLocale(): ?string
    {
        return $this->activeSite !== '' ? $this->activeSite : null;
    }

    /**
     * The default language — the topmost site by order. Single-language
     * installs (no sites) have no site at all, so their one tree
     * (locale = null) is always the origin.
     */
    protected function originLocale(): ?string
    {
        return $this->availableSites->first()?->handle;
    }

    public function isOriginLocale(): bool
    {
        return $this->activeLocale() === $this->originLocale();
    }

    /**
     * Keeps only the top-level fields whose value differs from $originData
     * — i.e. the ones this locale has actually been given its own value
     * for. Fields left untouched are dropped so they keep inheriting the
     * default language's value if it's edited later, instead of freezing a
     * copy of it the moment this locale is saved for the first time.
     */
    protected function overridesFrom(array $data, array $originData): array
    {
        return collect($data)
            ->reject(fn ($value, string $handle): bool => array_key_exists($handle, $originData) && $originData[$handle] == $value)
            ->all();
    }

    public function usesBlueprint(): bool
    {
        return $this->blueprintFields() !== [];
    }

    /**
     * Whether the user can edit this navigation's content — either the
     * umbrella "configure navigation" permission or the per-navigation
     * "edit {handle} navigation" permission (mount only requires "view ...",
     * since opening the page to read its content stays allowed).
     */
    public function canEdit(): bool
    {
        $user = auth()->user();

        return $user->hasPermission('configure navigation')
            || $user->hasPermission("edit {$this->navigation->handle} navigation");
    }

    #[Computed]
    public function blueprintFields(): array
    {
        return $this->navigation->effectiveBlueprint()?->data['fields'] ?? [];
    }

    #[Computed]
    public function blueprintTabs(): array
    {
        return $this->navigation->effectiveBlueprint()?->data['tabs'] ?? [['handle' => 'main', 'display' => 'Main']];
    }

    public function setActiveTab(string $handle): void
    {
        $this->activeTab = $handle;
    }

    /**
     * Recursively build a data array matching a field tree — see
     * GlobalSetForm::fillFieldsData(), which this mirrors.
     */
    protected function fillFieldsData(array $fields, ?array $existingData): array
    {
        $data = [];

        foreach ($fields as $field) {
            if ($field['type'] === 'replicator') {
                $existingRows = $existingData[$field['handle']] ?? [];
                $data[$field['handle']] = array_map(
                    fn (array $row): array => array_merge(
                        ['_uid' => $row['_uid'] ?? (string) Str::uuid()],
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
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $field = $this->replicatorFieldAt($path);

        if (! $field) {
            return;
        }

        $rows = &$this->dataAt($path);
        $rows[] = array_merge(['_uid' => (string) Str::uuid()], $this->fillFieldsData($field['replicator_fields'] ?? [], null));
    }

    public function removeReplicatorRow(array $path, int $rowIndex): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $rows = &$this->dataAt($path);
        unset($rows[$rowIndex]);
        $rows = array_values($rows);
    }

    public function duplicateReplicatorRow(array $path, int $rowIndex): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $rows = &$this->dataAt($path);

        if (! array_key_exists($rowIndex, $rows)) {
            return;
        }

        $duplicate = $rows[$rowIndex];
        $duplicate['_uid'] = (string) Str::uuid();
        array_splice($rows, $rowIndex + 1, 0, [$duplicate]);
    }

    /**
     * Drag-and-drop reorder handler for wire:sort (see partials/menu-list.blade.php).
     * $id is the dragged row's "_uid", $position its new zero-based index, and
     * $groupId is JSON `{"path": [...]}` identifying which rows array — each
     * nesting level gets its own wire:sort group, so a row can never be
     * dragged out of its own parent's list.
     */
    public function reorderReplicatorRow(string $id, int $position, string $groupId): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $path = json_decode($groupId, true)['path'] ?? [];
        $rows = &$this->dataAt($path);
        $oldIndex = $this->findRowIndexByUid($rows, $id);

        if ($oldIndex === null) {
            return;
        }

        $values = array_values($rows);
        $moved = array_splice($values, $oldIndex, 1)[0];
        array_splice($values, $position, 0, [$moved]);
        $rows = $values;
    }

    protected function findRowIndexByUid(array $rows, string $uid): ?int
    {
        foreach ($rows as $index => $row) {
            if (($row['_uid'] ?? null) === $uid) {
                return $index;
            }
        }

        return null;
    }

    public function removeAsset(array $path, int $assetId): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

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
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

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
            // ponytail: single-site navigations (activeSite = "") list all
            // sites' entries unscoped; scope only when the nav is
            // site-localized.
            ->when($this->activeSite !== '', fn ($query) => $query->where('site', $this->activeSite))
            ->orderBy('collection')
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

    /**
     * Terms a "taxonomy" field may pick from — every term of that taxonomy,
     * scoped to the active site when the navigation is site-localized.
     * Mirrors GlobalSetForm::taxonomyTerms().
     *
     * @return Collection<int, TaxonomyTerm>
     */
    public function taxonomyTerms(?string $taxonomyHandle)
    {
        if (! $taxonomyHandle) {
            return collect();
        }

        // ponytail: single-site navigations (activeSite = "") list all
        // sites' terms unscoped; scope only once the nav is site-localized.
        return TaxonomyTerm::where('taxonomy', $taxonomyHandle)
            ->when($this->activeSite !== '', fn ($query) => $query->where('site', $this->activeSite))
            ->orderBy('slug')
            ->get();
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
                default => 'string',
            };

            if (! empty($field['validation'])) {
                $fieldRules[] = $field['validation'];
            }

            $rules[$key] = $fieldRules;
        }

        return $rules;
    }

    /* ---------- Legacy tree mode (sidebar-admin only) ---------- */

    #[Computed]
    public function pages()
    {
        return Entry::orderBy('slug')->get(['id', 'slug', 'data']);
    }

    public function toggle(string $id): void
    {
        if (in_array($id, $this->expanded, true)) {
            $this->expanded = array_values(array_diff($this->expanded, [$id]));

            return;
        }

        $this->expanded[] = $id;
    }

    public function addRoot(): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $this->tree = $this->treeService()->withAddedItem($this->tree, null, $this->blankAttributes());
        $this->expanded[] = $this->tree[array_key_last($this->tree)]['id'];
    }

    public function addChild(string $parentId): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $this->tree = $this->treeService()->withAddedItem($this->tree, $parentId, $this->blankAttributes());

        $children = $this->treeService()->findItem($this->tree, $parentId)['children'];

        if (! in_array($parentId, $this->expanded, true)) {
            $this->expanded[] = $parentId;
        }

        $this->expanded[] = $children[array_key_last($children)]['id'];
    }

    public function duplicateItem(string $id): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $this->tree = $this->treeService()->withDuplicatedItem($this->tree, $id);
    }

    public function askDelete(string $id): void
    {
        $this->deletingId = $id;
        Flux::modal('confirm-delete-item')->show();
    }

    public function confirmDelete(): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        if ($this->deletingId === null) {
            return;
        }

        $this->tree = $this->treeService()->withRemovedItem($this->tree, $this->deletingId);
        $this->deletingId = null;

        Flux::modal('confirm-delete-item')->close();
    }

    /**
     * Reorders within the root list only. Cross-parent moves go through
     * askMove()/confirmMove() instead — dragging an item between separate
     * sortable lists (root <-> a parent's children) was unreliable and
     * could corrupt sibling data, so each list here only reorders itself.
     */
    public function sortRoot($id, $position): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $this->tree = $this->treeService()->withMovedItem($this->tree, $id, null, (int) $position);
    }

    /**
     * Reorders within one parent's children list only. See sortRoot().
     */
    public function sortChildren($id, $position, $parentId): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        $this->tree = $this->treeService()->withMovedItem($this->tree, $id, $parentId, (int) $position);
    }

    public function askMove(string $id): void
    {
        $this->movingId = $id;
        $this->moveTargetParentId = '';
        $this->moveChildrenStrategy = '';
        Flux::modal('move-item')->show();
    }

    /**
     * Root items that $movingId can be moved under, i.e. every root item
     * except itself (only root items can be parents, since depth is capped
     * at 2 levels).
     *
     * @return array<int, array>
     */
    #[Computed]
    public function moveTargets(): array
    {
        return collect($this->tree)
            ->reject(fn (array $item): bool => $item['id'] === $this->movingId)
            ->values()
            ->all();
    }

    public function movingItemHasChildren(): bool
    {
        if ($this->movingId === null) {
            return false;
        }

        $item = $this->treeService()->findItem($this->tree, $this->movingId);

        return $item !== null && $item['children'] !== [];
    }

    public function confirmMove(): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        if ($this->movingId === null) {
            return;
        }

        $service = $this->treeService();
        $tree = $this->tree;
        $targetParentId = $this->moveTargetParentId !== '' ? $this->moveTargetParentId : null;

        $movingItem = $service->findItem($tree, $this->movingId);

        if ($movingItem === null) {
            return;
        }

        if ($movingItem['children'] !== [] && $targetParentId !== null) {
            if ($this->moveChildrenStrategy === '') {
                Flux::toast(variant: 'danger', text: __("Choose what happens to this item's children first."));

                return;
            }

            $tree = $this->moveChildrenStrategy === 'promote'
                ? $service->withPromotedChildren($tree, $this->movingId)
                : $service->withClearedChildren($tree, $this->movingId);
        }

        $siblings = $targetParentId !== null
            ? $service->findItem($tree, $targetParentId)['children']
            : $tree;

        try {
            $this->tree = $service->withMovedItem($tree, $this->movingId, $targetParentId, count($siblings));
        } catch (InvalidArgumentException $e) {
            Flux::toast(variant: 'danger', text: $e->getMessage());

            return;
        }

        $this->movingId = null;
        Flux::modal('move-item')->close();
    }

    /**
     * Fields are bound directly to `$tree` (e.g. wire:model.blur="tree.0.title"),
     * so a type change lands here to normalize the type-dependent url/page_id
     * exclusivity in memory. Nothing here touches the database — only the
     * explicit save() action (or Ctrl+S) persists the tree.
     */
    public function updated(string $name): void
    {
        if (! str_starts_with($name, 'tree.') || ! str_ends_with($name, '.type')) {
            return;
        }

        $itemPath = Str::before(Str::after($name, 'tree.'), '.type');
        $type = data_get($this->tree, "{$itemPath}.type");

        data_set($this->tree, "{$itemPath}.page_id", $type === 'page' ? data_get($this->tree, "{$itemPath}.page_id") : null);
        data_set($this->tree, "{$itemPath}.url", $type === 'page' ? null : data_get($this->tree, "{$itemPath}.url"));
    }

    public function resolveUrl(array $item): ?string
    {
        return $this->treeService()->resolveUrl($item);
    }

    /**
     * @return array<string, mixed>
     */
    protected function blankAttributes(): array
    {
        return ['type' => 'internal', 'title' => '', 'url' => null, 'target' => '_self', 'roles' => []];
    }

    /**
     * Every role a sidebar item can be shown to (see sidebar.blade.php) —
     * only relevant for the "sidebar-admin" navigation, whose item edit
     * form (navigation-tree-editor.blade.php) offers this as a "Visible to"
     * checkbox list. A Super Admin role is left out — it already sees every
     * item regardless (see sidebar.blade.php's isSuper() bypass), so
     * checking it here would do nothing but suggest otherwise.
     *
     * @return Collection<int, Role>
     */
    #[Computed]
    public function availableRoles(): Collection
    {
        return Role::orderBy('title')->get()->reject(fn (Role $role): bool => in_array('super', $role->permissions, true))->values();
    }

    /* ---------- Shared ---------- */

    public function save(): void
    {
        auth()->user()->authorizePermission("edit {$this->navigation->handle} navigation");

        if ($this->usesBlueprint()) {
            $validated = $this->validate($this->fieldRules());
            $data = $this->stripRowUids($validated['data'] ?? []);

            if (! $this->isOriginLocale()) {
                $originData = $this->treeService()->load($this->navigation, $this->originLocale());
                // Compare against the origin's links translated to this
                // site, so an untouched picker (which shows the origin's
                // entry as this site's sibling) doesn't get stored as a
                // locale override and keeps live-following the default
                // language. A genuinely different entry still overrides.
                $originData = Entry::localizeLinkValues($originData, $this->blueprintFields(), $this->activeSite);
                $data = $this->overridesFrom($data, $originData);
            }

            $this->treeService()->save($this->navigation, $data, $this->activeLocale());

            Flux::toast(variant: 'success', text: __('Navigation saved.'));

            return;
        }

        $this->treeService()->save($this->navigation, $this->tree);

        Flux::toast(variant: 'success', text: __('Navigation saved.'));
    }

    protected function stripRowUids(array $data): array
    {
        unset($data['_uid']);

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->stripRowUids($value);
            }
        }

        return $data;
    }

    protected function treeService(): NavigationTreeService
    {
        return app(NavigationTreeService::class);
    }

    public function render(): View
    {
        return view($this->usesBlueprint()
            ? 'navigation::livewire.navigation-content-editor'
            : 'navigation::livewire.navigation-tree-editor');
    }
}
