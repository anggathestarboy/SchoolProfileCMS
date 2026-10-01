<?php

namespace App\Modules\Forms\Livewire;

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Forms\Models\Form;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Forms')]
class FormManager extends Component
{
    public ?int $editingId = null;

    public string $handle = '';

    public string $title = '';

    public string $blueprint = '';

    /**
     * Whether saving this (new) form should also create a blueprint for it,
     * namespaced "forms.{handle}", instead of requiring one to be picked
     * from $blueprint. Only relevant on create. Mirrors
     * GlobalSetManager::$createBlueprint.
     */
    public bool $createBlueprint = true;

    public bool $storeSubmissions = true;

    public string $redirectUrl = '';

    /**
     * Which field handles to show as columns in the submissions table.
     * Empty means every field.
     *
     * @var array<int, string>
     */
    public array $previewFields = [];

    public function mount(): void
    {
        auth()->user()->authorizePermission('configure forms');
    }

    #[Computed]
    public function forms()
    {
        return Form::orderBy('title')->get();
    }

    #[Computed]
    public function availableBlueprints()
    {
        return Blueprint::orderBy('namespace')->orderBy('handle')->get();
    }

    /**
     * The currently-picked blueprint's fields, used by the "Preview
     * columns" checkbox group. Empty while $createBlueprint is on (a
     * brand-new blueprint starts with no fields anyway).
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function selectedBlueprintFields(): array
    {
        if ($this->createBlueprint || $this->blueprint === '') {
            return [];
        }

        return Blueprint::where('handle', $this->blueprint)->value('data')['fields'] ?? [];
    }

    public function create(): void
    {
        auth()->user()->authorizePermission('configure forms');

        $this->reset(['editingId', 'handle', 'title', 'blueprint', 'redirectUrl', 'previewFields']);
        $this->createBlueprint = true;
        $this->storeSubmissions = true;
        Flux::modal('form-form')->show();
    }

    public function edit(int $id): void
    {
        auth()->user()->authorizePermission('configure forms');

        $form = Form::findOrFail($id);

        $this->editingId = $form->id;
        $this->handle = $form->handle;
        $this->title = $form->title;
        $this->blueprint = (string) ($form->settings['blueprint'] ?? $form->effectiveBlueprint()?->handle ?? '');
        $this->createBlueprint = false;
        $this->storeSubmissions = $form->storesSubmissions();
        $this->redirectUrl = (string) $form->redirectUrl();
        $this->previewFields = $form->previewFieldHandles();

        Flux::modal('form-form')->show();
    }

    public function regenerateHandle(): void
    {
        $this->handle = Str::slug($this->title, '_');
    }

    public function save(): void
    {
        auth()->user()->authorizePermission('configure forms');

        $validated = $this->validate([
            'handle' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('forms', 'handle')->ignore($this->editingId)],
            'title' => ['required', 'string', 'max:255'],
            'blueprint' => [$this->createBlueprint ? 'exclude' : 'nullable', 'string', Rule::exists('blueprints', 'handle')],
            'storeSubmissions' => ['boolean'],
            'redirectUrl' => ['nullable', 'url', 'max:2048'],
            'previewFields' => ['array'],
            'previewFields.*' => ['string'],
        ]);

        $form = Form::updateOrCreate(['id' => $this->editingId], [
            'handle' => $validated['handle'],
            'title' => $validated['title'],
            'settings' => [
                'blueprint' => $validated['blueprint'] ?? null,
                'store_submissions' => $validated['storeSubmissions'],
                'redirect_url' => $validated['redirectUrl'] ?: null,
                'preview_fields' => $validated['previewFields'],
            ],
        ]);

        $blueprintCreated = false;

        if ($this->createBlueprint) {
            $blueprint = Blueprint::firstOrCreate(
                ['namespace' => "forms.{$form->handle}", 'handle' => $form->handle],
                ['data' => ['tabs' => [['handle' => 'main', 'display' => 'Main', 'sections' => []]], 'fields' => []]]
            );
            $blueprintCreated = $blueprint->wasRecentlyCreated;
        }

        Flux::modal('form-form')->close();
        unset($this->forms);

        if ($blueprintCreated) {
            Flux::toast(variant: 'success', text: __('Form saved — now add fields to its blueprint below.'));
            $this->redirectRoute('blueprints.index', navigate: true);

            return;
        }

        Flux::toast(variant: 'success', text: __('Form saved.'));
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission('configure forms');

        $form = Form::findOrFail($id);
        $submissionCount = $form->submissions()->count();

        if ($submissionCount > 0) {
            Flux::toast(
                variant: 'danger',
                text: __('Cannot delete ":title" — it still has :count submissions. Delete those first.', [
                    'title' => $form->title,
                    'count' => $submissionCount,
                ]),
            );

            return;
        }

        $form->delete();

        Flux::toast(variant: 'success', text: __('Form deleted.'));

        unset($this->forms);
    }

    public function render(): View
    {
        return view('forms::livewire.form-manager');
    }
}
