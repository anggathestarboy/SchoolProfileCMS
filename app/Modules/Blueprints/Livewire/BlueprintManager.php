<?php

namespace App\Modules\Blueprints\Livewire;

use App\Modules\Blueprints\Models\Blueprint;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Blueprints')]
class BlueprintManager extends Component
{
    #[Computed]
    public function blueprints()
    {
        return Blueprint::orderBy('namespace')->orderBy('handle')->get();
    }

    /**
     * Blueprints grouped by the thing their namespace scopes them to
     * (collections.*, globals.*, taxonomies.*), plus an "other" group for
     * blueprints with no namespace yet — so the listing can show a heading
     * per category instead of one flat table.
     *
     * @return Collection<string, Collection<int, Blueprint>>
     */
    #[Computed]
    public function blueprintGroups()
    {
        return $this->blueprints->groupBy(function (Blueprint $blueprint): string {
            $prefix = explode('.', (string) $blueprint->namespace, 2)[0];

            return in_array($prefix, ['collections', 'globals', 'taxonomies', 'navigations', 'forms'], true) ? $prefix : 'other';
        });
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission('configure fields');

        Blueprint::findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('Blueprint deleted.'));

        unset($this->blueprints);
    }

    public function render(): View
    {
        return view('blueprints::livewire.blueprint-manager');
    }
}
