<?php

namespace App\Modules\Blueprints\Livewire;

use App\Models\Taxonomy;
use App\Modules\Blueprints\Models\Fieldset;
use App\Modules\Blueprints\Support\HasFieldBuilder;
use App\Modules\Collections\Models\Collection;
use App\Modules\Media\Models\AssetContainer;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A reusable group of fields (Statamic's "fieldset" concept) — unlike a
 * blueprint, a fieldset has no namespace and no tabs, just a flat list of
 * fields, meant to be imported into one or more blueprints later. Full-page
 * form, mirroring BlueprintForm.
 */
#[Title('Fieldsets')]
class FieldsetForm extends Component
{
    use HasFieldBuilder;

    /**
     * The single implicit tab every fieldset's fields belong to — fieldsets
     * have no tabs/sections of their own, but the shared field-editor
     * partial expects a $tabs list to check for a tab/section picker.
     */
    const TABS = [['handle' => 'main', 'display' => 'Main', 'sections' => []]];

    public ?int $editingId = null;

    public string $handle = '';

    public bool $fieldsCollapsedByDefault = false;

    public function updatedFieldsCollapsedByDefault(bool $value): void
    {
        $this->dispatch($value ? 'collapse-all-fields' : 'expand-all-fields');
    }

    #[Computed]
    public function availableAssetContainers()
    {
        return AssetContainer::orderBy('title')->get();
    }

    #[Computed]
    public function availableCollections()
    {
        return Collection::orderBy('title')->get();
    }

    #[Computed]
    public function availableFieldsets()
    {
        return Fieldset::orderBy('handle')->get();
    }

    #[Computed]
    public function availableTaxonomies()
    {
        return Taxonomy::orderBy('title')->get();
    }

    public function mount(?Fieldset $fieldset = null): void
    {
        auth()->user()->authorizePermission('configure fields');

        if (! $fieldset) {
            $this->addField();

            return;
        }

        $this->editingId = $fieldset->id;
        $this->handle = $fieldset->handle;
        $this->fieldsCollapsedByDefault = (bool) ($fieldset->data['fields_collapsed_by_default'] ?? false);
        $this->fields = collect($fieldset->data['fields'] ?? [])
            ->map(fn (array $field): array => $this->mapFieldFromStorage($field))
            ->all();

        if ($this->fields === []) {
            $this->addField();
        }
    }

    public function save(): void
    {
        auth()->user()->authorizePermission('configure fields');

        $this->fields = $this->normalizeFieldsTree($this->fields);

        $validated = $this->validate([
            'handle' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('fieldsets', 'handle')->ignore($this->editingId)],
        ]);

        $fieldErrors = $this->fields === [] ? ['fields' => __('Add at least one field.')] : [];
        $fieldErrors = array_merge($fieldErrors, $this->validateFieldsTree($this->fields, 'fields'));

        if ($fieldErrors !== []) {
            throw ValidationException::withMessages($fieldErrors);
        }

        $wasCreating = $this->editingId === null;

        $fieldset = Fieldset::updateOrCreate(['id' => $this->editingId], [
            'handle' => $validated['handle'],
            'data' => [
                'fields_collapsed_by_default' => $this->fieldsCollapsedByDefault,
                'fields' => array_map(fn (array $field): array => $this->normalizeFieldForStorage($field, topLevel: false), $this->fields),
            ],
        ]);

        $this->editingId = $fieldset->id;

        Flux::toast(variant: 'success', text: __('Fieldset saved.'));

        // Stay on this form after saving — only a brand-new fieldset needs
        // a redirect at all, since its URL still points at /create.
        if ($wasCreating) {
            $this->redirectRoute('blueprints.fieldsets.edit', ['fieldset' => $fieldset], navigate: true);
        }
    }

    public function render(): View
    {
        return view('blueprints::livewire.fieldset-form', [
            'fieldtypes' => static::$fieldtypes,
            'conditionOperators' => static::$conditionOperators,
            'assetContainers' => $this->availableAssetContainers,
            'linkableCollections' => $this->availableCollections,
            'fieldsets' => $this->availableFieldsets,
            'taxonomies' => $this->availableTaxonomies,
            'tabs' => self::TABS,
            'bardToolbarButtons' => static::$bardToolbarButtons,
        ])->title($this->editingId ? __('Edit fieldset') : __('New fieldset'));
    }
}
