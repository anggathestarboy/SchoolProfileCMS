<?php

namespace App\Modules\Forms\Livewire;

use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Form Submissions')]
class FormSubmissionManager extends Component
{
    public Form $form;

    public function mount(Form $form): void
    {
        auth()->user()->authorizePermission('configure forms');

        $this->form = $form;
    }

    /**
     * The blueprint's top-level fields shown as this table's columns —
     * mirrors how Statamic's submissions listing shows one column per
     * field instead of a raw JSON dump. Narrowed to the form's configured
     * "preview columns" (see Form::previewFieldHandles()) when set;
     * otherwise every field.
     *
     * @return array<int, array<string, mixed>>
     */
    #[Computed]
    public function fields(): array
    {
        $fields = $this->form->fields();
        $previewHandles = $this->form->previewFieldHandles();

        if ($previewHandles === []) {
            return $fields;
        }

        return collect($fields)->whereIn('handle', $previewHandles)->values()->all();
    }

    #[Computed]
    public function submissions()
    {
        return $this->form->submissions()->orderByDesc('id')->get();
    }

    public function delete(string $id): void
    {
        auth()->user()->authorizePermission('configure forms');

        FormSubmission::where('id', $id)->where('form', $this->form->handle)->delete();

        Flux::toast(variant: 'success', text: __('Submission deleted.'));

        unset($this->submissions);
    }

    public function render(): View
    {
        return view('forms::livewire.form-submission-manager');
    }
}
