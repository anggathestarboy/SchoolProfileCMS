<?php

namespace App\Modules\Media\Livewire;

use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Media\Services\MediaService;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Global, reusable asset picker modal: any component can open it by
 * dispatching "open-asset-picker" (path, container, selected, max) and listening
 * for "assets-selected" (path, ids) to receive the result. Renders once per
 * page (e.g. included in EntryForm's view) and serves every "assets" field
 * on that page, including ones nested inside replicators.
 */
class AssetPicker extends Component
{
    use WithFileUploads;

    /**
     * The field path (see EntryForm::dataAt()) the current selection should
     * be dispatched back to once confirmed.
     *
     * @var array<int, string|int>|null
     */
    public ?array $targetPath = null;

    public string $container = '';

    /**
     * @var array<int, int>
     */
    public array $selectedIds = [];

    /**
     * The "assets" field's own max_items, if configured — enforced here too
     * (not just by the field's own "+" button being hidden once already at
     * the limit) so a user can't select past it from inside an already
     * -open picker.
     */
    public ?int $maxSelectable = null;

    /**
     * The "assets" field's own validation rule (e.g. "image"), enforced here
     * too so a non-matching file can't even be uploaded from inside the
     * picker instead of only being rejected later when the entry is saved.
     */
    public ?string $validationRule = null;

    public string $folder = '';

    public string $search = '';

    public $uploads = [];

    /**
     * @param  array<int, string|int>  $path
     * @param  array<int, int>  $selected
     */
    #[On('open-asset-picker')]
    public function open(array $path, string $container, array $selected = [], ?int $max = null, ?string $validation = null): void
    {
        $this->targetPath = $path;
        $this->container = $container;
        $this->selectedIds = array_map('intval', $selected);
        $this->maxSelectable = $max;
        $this->validationRule = $validation ?: null;
        $this->folder = '';
        $this->search = '';

        unset($this->listing);

        Flux::modal('asset-picker')->show();
    }

    #[Computed]
    public function listing(): array
    {
        if ($this->container === '') {
            return ['folders' => collect(), 'files' => collect()];
        }

        $service = app(MediaService::class);

        if ($this->search !== '') {
            return $service->search($this->container, $this->search);
        }

        $listing = $service->listFolder($this->container, $this->folder);

        $listing['folders'] = $listing['folders']->map(fn (string $name): array => [
            'name' => $name,
            'path' => trim($this->folder.'/'.$name, '/'),
        ]);

        return $listing;
    }

    /**
     * The currently selected assets, regardless of which folder is being
     * browsed right now — shown as their own strip in the picker (see
     * asset-picker.blade.php) so a selection made from a different folder
     * (or already saved on the field before the picker was even opened)
     * still visibly counts toward the limit instead of silently occupying
     * a slot with nothing checked-looking on screen.
     *
     * @return Collection<int, AssetMeta>
     */
    #[Computed]
    public function selectedAssets()
    {
        if ($this->selectedIds === []) {
            return collect();
        }

        $assets = AssetMeta::whereIn('id', $this->selectedIds)->get()->keyBy('id');

        return collect($this->selectedIds)->map(fn (int $id) => $assets->get($id))->filter()->values();
    }

    #[Computed]
    public function breadcrumbs(): array
    {
        if ($this->folder === '') {
            return [];
        }

        $crumbs = [];
        $accumulated = '';

        foreach (explode('/', $this->folder) as $segment) {
            $accumulated = trim($accumulated.'/'.$segment, '/');
            $crumbs[] = ['name' => $segment, 'path' => $accumulated];
        }

        return $crumbs;
    }

    public function goToFolder(string $path): void
    {
        $this->folder = $path;
        $this->search = '';
        unset($this->listing);
    }

    public function toggle(int $assetId): void
    {
        if ($this->isSelected($assetId)) {
            $this->selectedIds = array_values(array_diff($this->selectedIds, [$assetId]));

            return;
        }

        if ($this->maxSelectable !== null && count($this->selectedIds) >= $this->maxSelectable) {
            Flux::toast(text: __('You can only select up to :max.', ['max' => $this->maxSelectable]), variant: 'warning');

            return;
        }

        $asset = AssetMeta::find($assetId);

        if ($asset && ! $this->isAssetAllowed($asset)) {
            Flux::toast(text: __('This file type is not allowed for this field.'), variant: 'warning');

            return;
        }

        $this->selectedIds = [...$this->selectedIds, $assetId];
    }

    public function isSelected(int $assetId): bool
    {
        return in_array($assetId, $this->selectedIds, true);
    }

    /**
     * Whether an already-uploaded asset satisfies the field's own validation
     * rule (e.g. "image") — checked both when toggling a selection and in
     * the grid so a mismatched file (a video under an "image" rule) can't be
     * picked from the library, not just blocked from being freshly uploaded.
     */
    public function isAssetAllowed(AssetMeta $asset): bool
    {
        if (! $this->validationRule) {
            return true;
        }

        $file = new File($asset->absolutePath());

        return ! Validator::make(['file' => $file], ['file' => $this->validationRule])->fails();
    }

    public function confirm(): void
    {
        $this->dispatch('assets-selected', path: $this->targetPath, ids: $this->selectedIds);

        Flux::modal('asset-picker')->close();
    }

    /**
     * Uploads land in the same container/folder being browsed via the
     * shared MediaService, so they show up in the full Media Library too —
     * and are auto-selected here to save the extra click.
     */
    public function updatedUploads(): void
    {
        if (empty($this->uploads) || $this->container === '') {
            return;
        }

        $uploadRules = ['required', 'file', 'max:51200'];

        if ($this->validationRule) {
            $uploadRules[] = $this->validationRule;
        }

        $this->validate(['uploads.*' => $uploadRules]);

        $service = app(MediaService::class);
        $failures = [];

        foreach ($this->uploads as $file) {
            try {
                $asset = $service->uploadFile($this->container, $this->folder, $file);
                $this->selectedIds[] = $asset->id;
            } catch (MediaException $e) {
                $failures[] = $e->getMessage();
            }
        }

        $this->uploads = [];
        unset($this->listing);

        if ($failures) {
            Flux::toast(text: implode(' ', $failures), variant: 'danger');
        } else {
            Flux::toast(text: __('Upload complete.'), variant: 'success');
        }
    }

    public function render(): View
    {
        return view('media::asset-picker');
    }
}
