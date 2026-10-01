<?php

namespace App\Modules\Blueprints\Livewire;

use App\Modules\Blueprints\Models\Fieldset;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * A reusable group of fields (Statamic's "fieldset" concept) — unlike a
 * blueprint, a fieldset has no namespace and no tabs, just a flat list of
 * fields, meant to be imported into one or more blueprints later.
 */
#[Title('Fieldsets')]
class FieldsetManager extends Component
{
    #[Computed]
    public function fieldsets()
    {
        return Fieldset::orderBy('handle')->get();
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission('configure fields');

        Fieldset::findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Fieldset deleted.'));

        unset($this->fieldsets);
    }

    public function render(): View
    {
        return view('blueprints::livewire.fieldset-manager');
    }
}
