<?php

namespace App\Modules\NotFoundDetector\Livewire;

use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\NotFoundDetector\Services\NotFoundScanner;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('404 Detector')]
class NotFoundDetector extends Component
{
    /**
     * @var array<int, string>
     */
    public array $selectedCollections = [];

    /**
     * @var array<int, array{entry: Entry, collection: ?Collection, url: ?string, reason: string}>|null
     */
    public ?array $results = null;

    public bool $hasScanned = false;

    #[Computed]
    public function availableCollections()
    {
        return Collection::orderBy('title')->get();
    }

    public function toggleAllCollections(): void
    {
        $allHandles = $this->availableCollections->pluck('handle')->all();

        $this->selectedCollections = count($this->selectedCollections) === count($allHandles) ? [] : $allHandles;
    }

    public function scan(): void
    {
        $this->results = app(NotFoundScanner::class)->scan($this->selectedCollections)->all();
        $this->hasScanned = true;
    }

    public function render(): View
    {
        return view('not-found-detector::livewire.not-found-detector');
    }
}
