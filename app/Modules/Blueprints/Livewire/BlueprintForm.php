<?php

namespace App\Modules\Blueprints\Livewire;

use App\Models\Taxonomy;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Blueprints\Models\Fieldset;
use App\Modules\Blueprints\Support\HasFieldBuilder;
use App\Modules\Collections\Models\Collection;
use App\Modules\Forms\Models\Form;
use App\Modules\Globals\Models\GlobalSet;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Navigation\Models\Navigation;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Blueprints')]
class BlueprintForm extends Component
{
    use HasFieldBuilder;

    public ?int $editingId = null;

    public string $namespace = '';

    public string $handle = '';

    /**
     * Dot-notation view name of the Blade template (under
     * resources/views/pages) used to render entries using this blueprint —
     * see availableTemplates().
     */
    public string $template = '';

    /**
     * Groups top-level fields for display, mirroring Statamic's blueprint
     * tabs. The first tab is always the reserved "main" tab (handle fixed,
     * not user-editable, cannot be removed) — every field defaults there.
     * Each tab may further group its own fields into "sections" — a
     * sub-heading within the tab (a field's "section" is only meaningful
     * within its own tab; an empty section means "ungrouped").
     *
     * @var array<int, array{handle: string, display: string, sections: array<int, array{handle: string, display: string}>}>
     */
    public array $tabs = [
        ['handle' => 'main', 'display' => 'Main', 'sections' => [
            ['handle' => 'main', 'display' => 'Main'],
        ]],
    ];

    /**
     * Index into $tabs currently shown in edit mode (handle/display inputs)
     * instead of as a plain button — null when no tab is being edited.
     */
    public ?int $editingTabIndex = null;

    /**
     * [tabIndex, sectionIndex] of the section currently shown in edit mode
     * — null when no section is being edited.
     *
     * @var array{0: int, 1: int}|null
     */
    public ?array $editingSection = null;

    /**
     * Index of a tab added via addTab() that hasn't been confirmed with
     * "Done" yet — null once confirmed, or when editing an existing tab
     * instead. Lets cancelTab() distinguish "discard this brand-new tab"
     * from "stop editing this already-saved one", regardless of whether
     * the still-unconfirmed tab was given a label/handle before Cancel.
     */
    public ?int $newTabIndex = null;

    /**
     * [tabIndex, sectionIndex] of a section added via addSection() that
     * hasn't been confirmed with "Done" yet. Mirrors $newTabIndex.
     *
     * @var array{0: int, 1: int}|null
     */
    public ?array $newSection = null;

    /**
     * Whether fields start collapsed when the page loads (see field-editor
     * .blade.php's x-data). Toggling this also dispatches the
     * expand/collapse-all-fields window event so already-rendered fields
     * follow it immediately, the same events the "Expand all"/"Collapse
     * all" buttons use.
     */
    public bool $fieldsCollapsedByDefault = false;

    public function updatedFieldsCollapsedByDefault(bool $value): void
    {
        $this->dispatch($value ? 'collapse-all-fields' : 'expand-all-fields');
    }

    public function mount(?Blueprint $blueprint = null): void
    {
        auth()->user()->authorizePermission('configure fields');

        if (! $blueprint) {
            $this->addField('main', 'main');

            return;
        }

        $this->editingId = $blueprint->id;
        $this->namespace = (string) $blueprint->namespace;
        $this->handle = $blueprint->handle;
        $this->template = (string) ($blueprint->data['template'] ?? '');
        $this->fieldsCollapsedByDefault = (bool) ($blueprint->data['fields_collapsed_by_default'] ?? false);

        $this->tabs = collect($blueprint->data['tabs'] ?? [])
            ->map(fn (array $tab): array => [
                'handle' => $tab['handle'] ?? '',
                'display' => $tab['display'] ?? '',
                'sections' => collect($tab['sections'] ?? [])
                    ->map(fn (array $section): array => ['handle' => $section['handle'] ?? '', 'display' => $section['display'] ?? ''])
                    ->all(),
            ])
            ->all();

        if ($this->tabs === [] || ($this->tabs[0]['handle'] ?? null) !== 'main') {
            array_unshift($this->tabs, ['handle' => 'main', 'display' => 'Main', 'sections' => []]);
        }

        $this->fields = collect($blueprint->data['fields'] ?? [])
            ->map(fn (array $field): array => $this->mapFieldFromStorage($field))
            ->all();

        if ($this->fields === []) {
            $this->addField();
        }
    }

    /**
     * Blueprint namespaces are derived from existing collections, global
     * sets, taxonomies and navigations (e.g. a "blog" collection gives the
     * namespace "collections.blog", a "site-settings" global set gives
     * "globals.site-settings"), mirroring how Statamic scopes blueprints to
     * the thing they belong to.
     */
    #[Computed]
    public function availableNamespaces()
    {
        $collectionNamespaces = Collection::orderBy('title')->get()
            ->map(fn (Collection $collection): array => [
                'value' => "collections.{$collection->handle}",
                'label' => "{$collection->title} (collections.{$collection->handle})",
            ]);

        $globalSetNamespaces = GlobalSet::orderBy('title')->get()
            ->map(fn (GlobalSet $globalSet): array => [
                'value' => "globals.{$globalSet->handle}",
                'label' => "{$globalSet->title} (globals.{$globalSet->handle})",
            ]);

        $taxonomyNamespaces = Taxonomy::orderBy('title')->get()
            ->map(fn (Taxonomy $taxonomy): array => [
                'value' => "taxonomies.{$taxonomy->handle}",
                'label' => "{$taxonomy->title} (taxonomies.{$taxonomy->handle})",
            ]);

        $navigationNamespaces = Navigation::orderBy('title')->get()
            ->map(fn (Navigation $navigation): array => [
                'value' => "navigations.{$navigation->handle}",
                'label' => "{$navigation->title} (navigations.{$navigation->handle})",
            ]);

        $formNamespaces = Form::orderBy('title')->get()
            ->map(fn (Form $form): array => [
                'value' => "forms.{$form->handle}",
                'label' => "{$form->title} (forms.{$form->handle})",
            ]);

        return $collectionNamespaces->concat($globalSetNamespaces)->concat($taxonomyNamespaces)->concat($navigationNamespaces)->concat($formNamespaces)->values();
    }

    /**
     * Whether this blueprint's namespace is a collection with its "Schema"
     * tab enabled (see Collection::schemaEnabled()) — used to show a
     * non-editable "Schema" tab indicator alongside the real, storable
     * tabs, since that tab's fields are fixed in code, not configured here.
     */
    #[Computed]
    public function schemaTabVisible(): bool
    {
        if (! str_starts_with($this->namespace, 'collections.')) {
            return false;
        }

        $handle = substr($this->namespace, strlen('collections.'));

        return (bool) Collection::where('handle', $handle)->first()?->schemaEnabled();
    }

    /**
     * Same as schemaTabVisible(), but for the "SEO" tab (see
     * Collection::seoEnabled()).
     */
    #[Computed]
    public function seoTabVisible(): bool
    {
        if (! str_starts_with($this->namespace, 'collections.')) {
            return false;
        }

        $handle = substr($this->namespace, strlen('collections.'));

        return (bool) Collection::where('handle', $handle)->first()?->seoEnabled();
    }

    /**
     * Asset containers an "assets" field can be scoped to (see the Media
     * module's asset_containers table).
     */
    #[Computed]
    public function availableAssetContainers()
    {
        return AssetContainer::orderBy('title')->get();
    }

    /**
     * Collections a "link" field's allow-list can be scoped to.
     */
    #[Computed]
    public function availableCollections()
    {
        return Collection::orderBy('title')->get();
    }

    /**
     * Fieldsets a "fieldset" field can import (see the Fieldsets manager —
     * same table/module, a reusable group of fields).
     */
    #[Computed]
    public function availableFieldsets()
    {
        return Fieldset::orderBy('handle')->get();
    }

    /**
     * Taxonomies a "taxonomy" field can be scoped to.
     */
    #[Computed]
    public function availableTaxonomies()
    {
        return Taxonomy::orderBy('title')->get();
    }

    /**
     * Blade templates a blueprint can render entries with — every
     * *.blade.php file under resources/views/pages, as the dot-notation
     * view name Laravel's view() expects (e.g. "pages.dynamic.home").
     *
     * @return array<int, string>
     */
    #[Computed]
    public function availableTemplates(): array
    {
        $directory = resource_path('views/pages');

        if (! File::isDirectory($directory)) {
            return [];
        }

        return collect(File::allFiles($directory))
            ->filter(fn ($file): bool => str_ends_with($file->getFilename(), '.blade.php'))
            ->map(fn ($file): string => 'pages.'.str_replace(
                ['/', '.blade.php'],
                ['.', ''],
                $file->getRelativePathname()
            ))
            ->sort()
            ->values()
            ->all();
    }

    public function addTab(): void
    {
        $this->tabs[] = ['handle' => '', 'display' => '', 'sections' => []];
        $this->editingTabIndex = count($this->tabs) - 1;
        $this->newTabIndex = $this->editingTabIndex;
    }

    /**
     * Confirms the tab currently being edited (the "Done" button) — clears
     * $newTabIndex too, so a freshly-added tab counts as saved from this
     * point on and Cancel will no longer discard it.
     */
    public function confirmTab(): void
    {
        if ($this->editingTabIndex === $this->newTabIndex) {
            $this->newTabIndex = null;
        }

        $this->editingTabIndex = null;
    }

    /**
     * Leaves the tab's edit mode without saving. A brand-new, not-yet
     * -confirmed tab (see $newTabIndex) is removed entirely regardless of
     * whatever label/handle was typed into it — Cancel means "I don't want
     * to create this tab after all". Canceling an already-confirmed tab
     * just exits edit mode, leaving it as it was.
     */
    public function cancelTab(int $index): void
    {
        if ($this->newTabIndex === $index) {
            $this->newTabIndex = null;
            $this->removeTab($index);

            return;
        }

        $this->editingTabIndex = null;
    }

    /**
     * Removing a tab reassigns any top-level fields in it back to "main" so
     * they're never orphaned under a tab that no longer exists.
     */
    public function removeTab(int $index): void
    {
        if ($index === 0) {
            return;
        }

        $handle = $this->tabs[$index]['handle'] ?? null;

        foreach ($this->fields as &$field) {
            if (($field['tab'] ?? 'main') === $handle) {
                $field['tab'] = 'main';
                $field['section'] = '';
            }
        }
        unset($field);

        unset($this->tabs[$index]);
        $this->tabs = array_values($this->tabs);

        if ($this->editingTabIndex === $index) {
            $this->editingTabIndex = null;
        }

        if ($this->editingSection !== null && $this->editingSection[0] === $index) {
            $this->editingSection = null;
        }
    }

    public function addSection(int $tabIndex): void
    {
        $this->tabs[$tabIndex]['sections'][] = ['handle' => '', 'display' => ''];
        $this->editingSection = [$tabIndex, count($this->tabs[$tabIndex]['sections']) - 1];
        $this->newSection = $this->editingSection;
    }

    /**
     * Confirms the section currently being edited (the "Done" button) —
     * clears $newSection too, so a freshly-added section counts as saved
     * from this point on and Cancel will no longer discard it.
     */
    public function confirmSection(): void
    {
        if ($this->editingSection === $this->newSection) {
            $this->newSection = null;
        }

        $this->editingSection = null;
    }

    /**
     * Leaves the section's edit mode without saving. A brand-new, not-yet
     * -confirmed section (see $newSection) is removed entirely regardless
     * of whatever label/handle was typed into it — Cancel means "I don't
     * want to create this section after all". Canceling an already
     * -confirmed section just exits edit mode, leaving it as it was.
     */
    public function cancelSection(int $tabIndex, int $sectionIndex): void
    {
        if ($this->newSection === [$tabIndex, $sectionIndex]) {
            $this->newSection = null;
            $this->removeSection($tabIndex, $sectionIndex);

            return;
        }

        $this->editingSection = null;
    }

    /**
     * Removing a section ungroups any fields in it (within that tab) back
     * to no section, rather than orphaning them.
     */
    public function removeSection(int $tabIndex, int $sectionIndex): void
    {
        $tabHandle = $this->tabs[$tabIndex]['handle'] ?? null;
        $sectionHandle = $this->tabs[$tabIndex]['sections'][$sectionIndex]['handle'] ?? null;

        foreach ($this->fields as &$field) {
            if (($field['tab'] ?? 'main') === $tabHandle && ($field['section'] ?? '') === $sectionHandle) {
                $field['section'] = '';
            }
        }
        unset($field);

        unset($this->tabs[$tabIndex]['sections'][$sectionIndex]);
        $this->tabs[$tabIndex]['sections'] = array_values($this->tabs[$tabIndex]['sections']);

        if ($this->editingSection === [$tabIndex, $sectionIndex]) {
            $this->editingSection = null;
        }
    }

    /**
     * Drag-and-drop reorder handler for a tab's sections list (see
     * blueprint-form.blade.php's wire:sort container around
     * $tab["sections"]). $id is the dragged section's index within the tab
     * at the time the drag started, $groupId is that tab's index (passed via
     * wire:sort:group-id, one group per tab so sections can't be dragged
     * across tabs). Fields reference their section by handle, not index, so
     * reordering sections never disturbs which fields belong to which.
     */
    public function reorderSections(string $id, int $position, string $groupId): void
    {
        $tabIndex = (int) $groupId;
        $sections = $this->tabs[$tabIndex]['sections'] ?? [];
        $oldIndex = (int) $id;

        if (! array_key_exists($oldIndex, $sections)) {
            return;
        }

        $moved = array_splice($sections, $oldIndex, 1)[0];
        $position = max(0, min($position, count($sections)));
        array_splice($sections, $position, 0, [$moved]);

        $this->tabs[$tabIndex]['sections'] = array_values($sections);
        $this->editingSection = null;
    }

    /**
     * Validate $this->tabs (forcing index 0 to always be the reserved
     * "main" tab, not user-editable) and each tab's own sections, requiring
     * unique, valid handles throughout.
     *
     * @return array{0: array<string, string>, 1: array<int, string>, 2: array<string, array<int, string>>}
     */
    protected function validateTabs(): array
    {
        $this->tabs[0]['handle'] = 'main';
        $this->tabs[0]['display'] = 'Main';

        $errors = [];
        $tabHandles = ['main'];
        $sectionHandlesByTab = [];

        foreach ($this->tabs as $index => $tab) {
            if ($index !== 0) {
                $tabHandle = trim($tab['handle'] ?? '');

                if ($tabHandle === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $tabHandle) || $tabHandle === 'main') {
                    $errors["tabs.{$index}.handle"] = __('Tab handle must be lowercase letters, numbers and underscores, starting with a letter, and not "main".');
                } elseif (in_array($tabHandle, $tabHandles, true)) {
                    $errors["tabs.{$index}.handle"] = __('Tab handles must be unique.');
                } else {
                    $tabHandles[] = $tabHandle;
                }
            } else {
                $tabHandle = 'main';
            }

            $sectionHandles = [];

            foreach ($tab['sections'] ?? [] as $sectionIndex => $section) {
                $sectionHandle = trim($section['handle'] ?? '');

                if ($sectionHandle === '' || ! preg_match('/^[a-z][a-z0-9_]*$/', $sectionHandle)) {
                    $errors["tabs.{$index}.sections.{$sectionIndex}.handle"] = __('Section handle must be lowercase letters, numbers and underscores, starting with a letter.');
                } elseif (in_array($sectionHandle, $sectionHandles, true)) {
                    $errors["tabs.{$index}.sections.{$sectionIndex}.handle"] = __('Section handles must be unique within a tab.');
                } else {
                    $sectionHandles[] = $sectionHandle;
                }
            }

            $sectionHandlesByTab[$tabHandle] = $sectionHandles;
        }

        return [$errors, $tabHandles, $sectionHandlesByTab];
    }

    public function save(): void
    {
        auth()->user()->authorizePermission('configure fields');

        $this->fields = $this->normalizeFieldsTree($this->fields);

        $validated = $this->validate([
            'namespace' => [
                'nullable', 'string', 'max:255',
                Rule::in($this->availableNamespaces->pluck('value')->all()),
            ],
            'handle' => [
                'required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('blueprints', 'handle')
                    ->where('namespace', $this->namespace ?: null)
                    ->ignore($this->editingId),
            ],
            'template' => ['nullable', 'string', Rule::in($this->availableTemplates)],
        ]);

        [$tabErrors, $tabHandles, $sectionHandlesByTab] = $this->validateTabs();

        $fieldErrors = $this->fields === [] ? ['fields' => __('Add at least one field.')] : [];
        $fieldErrors = array_merge($tabErrors, $fieldErrors, $this->validateFieldsTree($this->fields, 'fields', $tabHandles, $sectionHandlesByTab));

        if ($fieldErrors !== []) {
            throw ValidationException::withMessages($fieldErrors);
        }

        $data = [
            'template' => $validated['template'] ?: null,
            'fields_collapsed_by_default' => $this->fieldsCollapsedByDefault,
            'tabs' => array_map(fn (array $tab): array => [
                'handle' => $tab['handle'],
                'display' => $tab['display'] ?: Str::headline($tab['handle']),
                'sections' => array_map(fn (array $section): array => [
                    'handle' => $section['handle'],
                    'display' => $section['display'] ?: Str::headline($section['handle']),
                ], $tab['sections'] ?? []),
            ], $this->tabs),
            'fields' => array_map(fn (array $field): array => $this->normalizeFieldForStorage($field), $this->fields),
        ];

        $wasCreating = $this->editingId === null;

        $blueprint = Blueprint::updateOrCreate(['id' => $this->editingId], [
            'namespace' => $this->namespace ?: null,
            'handle' => $validated['handle'],
            'data' => $data,
        ]);

        $this->editingId = $blueprint->id;

        Flux::toast(variant: 'success', text: __('Blueprint saved.'));

        // Stay on this form after saving — only a brand-new blueprint needs
        // a redirect at all, since its URL still points at /create.
        if ($wasCreating) {
            $this->redirectRoute('blueprints.edit', ['blueprint' => $blueprint], navigate: true);
        }
    }

    public function render(): View
    {
        return view('blueprints::livewire.blueprint-form', [
            'fieldtypes' => static::$fieldtypes,
            'conditionOperators' => static::$conditionOperators,
            'assetContainers' => $this->availableAssetContainers,
            'linkableCollections' => $this->availableCollections,
            'fieldsets' => $this->availableFieldsets,
            'taxonomies' => $this->availableTaxonomies,
            'templates' => $this->availableTemplates,
            'bardToolbarButtons' => static::$bardToolbarButtons,
        ])->title($this->editingId ? __('Edit blueprint') : __('New blueprint'));
    }
}
