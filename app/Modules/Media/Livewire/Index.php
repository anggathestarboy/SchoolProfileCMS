<?php

namespace App\Modules\Media\Livewire;

use App\Modules\Media\Exceptions\MediaException;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Media\Services\MediaService;
use Flux\Flux;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Title('Media Library')]
class Index extends Component
{
    use WithFileUploads;

    #[Url(as: 'container')]
    public string $container = '';

    #[Url]
    public string $path = '';

    public string $viewMode = 'grid';

    public string $search = '';

    public array $clipboard = [];

    public array $selected = [];

    public $uploads = [];

    public string $newFolderName = '';

    public bool $showCreateFolder = false;

    public string $renameFolderPath = '';

    public string $renameFolderValue = '';

    public bool $showRenameFolder = false;

    public string $deleteFolderPath = '';

    public bool $showDeleteFolder = false;

    public bool $deleteFolderHasContents = false;

    public bool $showBulkDelete = false;

    public int $deleteFileId = 0;

    public bool $showDeleteFile = false;

    public int $renameFileId = 0;

    public string $renameFileValue = '';

    public string $renameFileExtension = '';

    public bool $showRenameFile = false;

    public ?int $previewAssetId = null;

    public bool $showPreview = false;

    public string $previewTitle = '';

    public string $previewAlt = '';

    public string $previewLoading = 'lazy';

    public function mount(): void
    {
        if ($this->container === '' || ! AssetContainer::where('handle', $this->container)->exists()) {
            // Default to the first container this user may view — a user
            // with no view rights anywhere gets an empty (no containers yet)
            // screen instead of a 403 on a container they can't open.
            $this->container = $this->viewableContainerHandles()[0] ?? '';
        }

        if ($this->container !== '') {
            auth()->user()->authorizePermission("view {$this->container} assets");
        }
    }

    public function updatedContainer(): void
    {
        if ($this->container !== '') {
            auth()->user()->authorizePermission("view {$this->container} assets");
        }

        $this->path = '';
        $this->search = '';
        $this->selected = [];
        unset($this->listing);
    }

    #[Computed]
    public function containers(): Collection
    {
        return AssetContainer::orderBy('title')
            ->get()
            ->filter(fn (AssetContainer $container): bool => $this->can("view {$container->handle} assets"))
            ->values();
    }

    /**
     * Handles of every container this user may view, in title order — the
     * mount() default and the container switcher both draw from it.
     *
     * @return array<int, string>
     */
    protected function viewableContainerHandles(): array
    {
        return $this->containers->pluck('handle')->all();
    }

    /**
     * Whether the current user has $permission — a thin wrapper for this
     * component's own use in blades (gate checks are per-container strings,
     * not fixed permission names).
     */
    public function can(string $permission): bool
    {
        return auth()->user()->hasPermission($permission);
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

        $listing = $service->listFolder($this->container, $this->path);

        $listing['folders'] = $listing['folders']->map(
            fn (string $name) => [
                'name' => $name,
                'path' => trim($this->path.'/'.$name, '/'),
            ],
        );

        return $listing;
    }

    public function updatedSearch(): void
    {
        $this->selected = [];
    }

    #[Computed]
    public function breadcrumbs(): array
    {
        if ($this->path === '') {
            return [];
        }

        $segments = explode('/', $this->path);
        $crumbs = [];
        $accumulated = '';

        foreach ($segments as $segment) {
            $accumulated = trim($accumulated.'/'.$segment, '/');
            $crumbs[] = ['name' => $segment, 'path' => $accumulated];
        }

        return $crumbs;
    }

    #[Computed]
    public function previewAsset(): ?AssetMeta
    {
        return $this->previewAssetId ? AssetMeta::where('container', $this->container)->find($this->previewAssetId) : null;
    }

    public function goToPath(string $path): void
    {
        $this->path = $path;
        $this->search = '';
        $this->selected = [];
        unset($this->listing);
    }

    public function toggleSelect(string $type, string $key): void
    {
        $itemKey = "{$type}:{$key}";

        if (in_array($itemKey, $this->selected, true)) {
            $this->selected = array_values(array_diff($this->selected, [$itemKey]));
        } else {
            $this->selected[] = $itemKey;
        }
    }

    public function isSelected(string $type, string $key): bool
    {
        return in_array("{$type}:{$key}", $this->selected, true);
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function startCreateFolder(): void
    {
        $this->newFolderName = '';
        $this->showCreateFolder = true;
    }

    public function createFolder(): void
    {
        auth()->user()->authorizePermission("edit folders {$this->container} assets");

        $this->validate(['newFolderName' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/']]);

        try {
            app(MediaService::class)->createFolder($this->container, trim($this->path.'/'.$this->newFolderName, '/'));
            $this->showCreateFolder = false;
            unset($this->listing);
            Flux::toast(text: 'Folder created.', variant: 'success');
        } catch (MediaException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
        }
    }

    public function startRenameFolder(string $path): void
    {
        $this->renameFolderPath = $path;
        $this->renameFolderValue = basename($path);
        $this->showRenameFolder = true;
    }

    public function renameFolder(): void
    {
        auth()->user()->authorizePermission("edit folders {$this->container} assets");

        $this->validate(['renameFolderValue' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/']]);

        $parent = trim(dirname($this->renameFolderPath), '.');
        $newPath = trim($parent.'/'.$this->renameFolderValue, '/');

        try {
            app(MediaService::class)->renameFolder($this->container, $this->renameFolderPath, $newPath);
            $this->showRenameFolder = false;
            $this->clearSelection();
            unset($this->listing);
            Flux::toast(text: 'Folder renamed.', variant: 'success');
        } catch (MediaException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
        }
    }

    public function askDeleteFolder(string $path): void
    {
        $listing = app(MediaService::class)->listFolder($this->container, $path);
        $this->deleteFolderHasContents = $listing['folders']->isNotEmpty() || $listing['files']->isNotEmpty();
        $this->deleteFolderPath = $path;
        $this->showDeleteFolder = true;
    }

    public function confirmDeleteFolder(): void
    {
        auth()->user()->authorizePermission("edit folders {$this->container} assets");

        app(MediaService::class)->deleteFolder($this->container, $this->deleteFolderPath);
        $this->showDeleteFolder = false;
        unset($this->listing);
        Flux::toast(text: 'Folder deleted.', variant: 'success');
    }

    public function duplicateFolder(string $path): void
    {
        $this->duplicateItems([['type' => 'folder', 'key' => $path]]);
    }

    public function duplicateFile(int $assetId): void
    {
        $this->duplicateItems([['type' => 'file', 'key' => $assetId]]);
    }

    public function cutFolder(string $path): void
    {
        $this->cutItems([['type' => 'folder', 'key' => $path]]);
    }

    public function cutFile(int $assetId): void
    {
        $this->cutItems([['type' => 'file', 'key' => $assetId]]);
    }

    public function askDeleteFile(int $assetId): void
    {
        $this->deleteFileId = $assetId;
        $this->showDeleteFile = true;
    }

    public function confirmDeleteFile(): void
    {
        $this->deleteItems([['type' => 'file', 'key' => $this->deleteFileId]]);
        $this->showDeleteFile = false;
    }

    public function startRenameFile(int $assetId): void
    {
        $asset = AssetMeta::where('container', $this->container)->findOrFail($assetId);
        $this->renameFileId = $asset->id;
        $this->renameFileValue = $asset->filename;
        $this->renameFileExtension = $asset->extension;
        $this->showRenameFile = true;
    }

    public function renameFile(): void
    {
        auth()->user()->authorizePermission("rename {$this->container} assets");

        $this->validate(['renameFileValue' => ['required', 'string', 'max:255', 'regex:/^[^\/\\\\]+$/']]);

        $asset = AssetMeta::where('container', $this->container)->findOrFail($this->renameFileId);
        $newBasename = $asset->extension !== '' ? "{$this->renameFileValue}.{$asset->extension}" : $this->renameFileValue;

        try {
            app(MediaService::class)->renameFile($this->container, $asset, $newBasename);
            $this->showRenameFile = false;
            $this->clearSelection();
            unset($this->listing);
            Flux::toast(text: 'File renamed.', variant: 'success');
        } catch (MediaException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
        }
    }

    public function dropOnFolder(string $draggedType, string $draggedKey, string $targetPath): void
    {
        auth()->user()->authorizePermission("move {$this->container} assets");

        if ($draggedType === 'folder' && ($draggedKey === $targetPath || str_starts_with($targetPath, $draggedKey.'/'))) {
            return;
        }

        $service = app(MediaService::class);
        $moved = false;

        try {
            if ($draggedType === 'file') {
                $asset = AssetMeta::where('container', $this->container)->find((int) $draggedKey);

                if ($asset && $asset->folder !== $targetPath) {
                    $service->moveFile($this->container, $asset, $targetPath);
                    $moved = true;
                }
            } else {
                $to = trim($targetPath.'/'.basename($draggedKey), '/');

                if ($to !== $draggedKey) {
                    $service->moveFolder($this->container, $draggedKey, $to);
                    $moved = true;
                }
            }

            if ($moved) {
                unset($this->listing);
                Flux::toast(text: 'Moved.', variant: 'success');
            }
        } catch (MediaException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
        }
    }

    public function renameSelected(): void
    {
        $items = $this->selectedItems();

        if (count($items) !== 1) {
            return;
        }

        $item = $items[0];

        if ($item['type'] === 'file') {
            $this->startRenameFile($item['key']);
        } else {
            $this->startRenameFolder($item['key']);
        }
    }

    public function duplicateSelected(): void
    {
        $this->duplicateItems($this->selectedItems());
        $this->clearSelection();
    }

    public function cutSelected(): void
    {
        $this->cutItems($this->selectedItems());
    }

    public function copySelected(): void
    {
        $this->copyItems($this->selectedItems());
    }

    public function askDeleteSelected(): void
    {
        $this->showBulkDelete = true;
    }

    public function confirmDeleteSelected(): void
    {
        $this->deleteItems($this->selectedItems());
        $this->clearSelection();
        $this->showBulkDelete = false;
    }

    public function paste(): void
    {
        if (empty($this->clipboard)) {
            return;
        }

        $service = app(MediaService::class);
        $mode = $this->clipboard['mode'];

        if ($mode === 'cut') {
            auth()->user()->authorizePermission("move {$this->container} assets");
        } else {
            $this->authorizeItemTypes($this->clipboard['items'], "edit {$this->container} assets", "edit folders {$this->container} assets");
        }

        try {
            foreach ($this->clipboard['items'] as $item) {
                if ($item['type'] === 'folder') {
                    if ($mode === 'cut') {
                        $to = trim($this->path.'/'.basename((string) $item['key']), '/');
                        $service->moveFolder($this->container, $item['key'], $to);
                    } else {
                        $service->copyFolderTo($this->container, $item['key'], $this->path);
                    }
                } else {
                    $asset = AssetMeta::where('container', $this->container)->find($item['key']);

                    if (! $asset) {
                        continue;
                    }

                    if ($mode === 'cut') {
                        $service->moveFile($this->container, $asset, $this->path);
                    } else {
                        $service->copyFileTo($this->container, $asset, $this->path);
                    }
                }
            }

            $this->clipboard = [];
            unset($this->listing);
            Flux::toast(text: 'Done.', variant: 'success');
        } catch (MediaException $e) {
            Flux::toast(text: $e->getMessage(), variant: 'danger');
        }
    }

    public function updatedUploads(): void
    {
        if (empty($this->uploads)) {
            return;
        }

        auth()->user()->authorizePermission("upload {$this->container} assets");

        $this->validate(['uploads.*' => ['required', 'file', 'max:51200']]);

        $service = app(MediaService::class);
        $failures = [];

        foreach ($this->uploads as $file) {
            try {
                $service->uploadFile($this->container, $this->path, $file);
            } catch (MediaException $e) {
                $failures[] = $e->getMessage();
            }
        }

        $this->uploads = [];
        unset($this->listing);

        if ($failures) {
            Flux::toast(text: implode(' ', $failures), variant: 'danger');
        } else {
            Flux::toast(text: 'Upload complete.', variant: 'success');
        }
    }

    public function openPreview(int $assetId): void
    {
        $asset = AssetMeta::where('container', $this->container)->findOrFail($assetId);

        $this->previewAssetId = $asset->id;
        $this->previewTitle = $asset->title;
        $this->previewAlt = $asset->alt;
        $this->previewLoading = $asset->loading;
        $this->showPreview = true;
    }

    public function savePreviewMeta(): void
    {
        auth()->user()->authorizePermission("edit {$this->container} assets");

        $this->validate([
            'previewTitle' => ['nullable', 'string', 'max:255'],
            'previewAlt' => ['nullable', 'string', 'max:255'],
            'previewLoading' => ['required', 'in:lazy,eager'],
        ]);

        $asset = AssetMeta::where('container', $this->container)->findOrFail($this->previewAssetId);

        $meta = $asset->meta ?? [];
        $meta['data']['title'] = $this->previewTitle;
        $meta['data']['alt'] = $this->previewAlt;
        $meta['data']['loading'] = $this->previewLoading;

        $asset->update(['meta' => $meta]);

        $this->showPreview = false;
        unset($this->listing);
        Flux::toast(text: 'Saved.', variant: 'success');
    }

    public function downloadFile(int $assetId)
    {
        $asset = AssetMeta::where('container', $this->container)->findOrFail($assetId);

        return app(MediaService::class)->downloadFile($asset);
    }

    public function downloadFolder(string $path)
    {
        return app(MediaService::class)->downloadFolder($this->container, $path);
    }

    /**
     * Selections and clipboard pastes can mix files and folders in one
     * batch, so authorize whichever permission each type present actually
     * needs before touching storage — checked up front so a partially
     * unauthorized batch aborts before anything is mutated.
     *
     * @param  array<int, array{type: string, key: int|string}>  $items
     */
    protected function authorizeItemTypes(array $items, string $filePermission, string $folderPermission): void
    {
        $types = array_unique(array_column($items, 'type'));

        if (in_array('file', $types, true)) {
            auth()->user()->authorizePermission($filePermission);
        }

        if (in_array('folder', $types, true)) {
            auth()->user()->authorizePermission($folderPermission);
        }
    }

    protected function selectedItems(): array
    {
        return collect($this->selected)
            ->map(function (string $item) {
                [$type, $key] = explode(':', $item, 2);

                return ['type' => $type, 'key' => $type === 'file' ? (int) $key : $key];
            })
            ->all();
    }

    /**
     * Whether the current clipboard can be pasted by this user: a cut needs
     * the move permission, a copy needs the edit perms for whatever item
     * types it holds (files and/or folders).
     */
    public function canPaste(): bool
    {
        if (empty($this->clipboard) || $this->container === '') {
            return false;
        }

        $user = auth()->user();

        if (($this->clipboard['mode'] ?? 'cut') === 'cut') {
            return $user->hasPermission("move {$this->container} assets");
        }

        $hasFile = collect($this->clipboard['items'] ?? [])->contains('type', 'file');
        $hasFolder = collect($this->clipboard['items'] ?? [])->contains('type', 'folder');

        return (! $hasFile || $user->hasPermission("edit {$this->container} assets"))
            && (! $hasFolder || $user->hasPermission("edit folders {$this->container} assets"));
    }

    /**
     * Whether every currently selected item is within reach of $filePermission
     * and $folderPermission (a folder item is checked against the folder
     * permission, a file against the file one) — the boolean counterpart of
     * authorizeItemTypes(), for gating the selection-bar buttons.
     */
    public function canSelected(string $filePermission, string $folderPermission): bool
    {
        $items = $this->selectedItems();

        if ($items === []) {
            return false;
        }

        $user = auth()->user();

        foreach ($items as $item) {
            $permission = $item['type'] === 'folder' ? $folderPermission : $filePermission;

            if (! $user->hasPermission($permission)) {
                return false;
            }
        }

        return true;
    }

    protected function cutItems(array $items): void
    {
        $this->clipboard = ['mode' => 'cut', 'items' => $items];
        $this->clearSelection();
        Flux::toast(text: 'Cut. Navigate to a destination folder and paste.', variant: 'success');
    }

    protected function copyItems(array $items): void
    {
        $this->clipboard = ['mode' => 'copy', 'items' => $items];
        $this->clearSelection();
        Flux::toast(text: 'Copied. Navigate to a destination folder and paste.', variant: 'success');
    }

    protected function duplicateItems(array $items): void
    {
        $this->authorizeItemTypes($items, "edit {$this->container} assets", "edit folders {$this->container} assets");

        $service = app(MediaService::class);

        foreach ($items as $item) {
            if ($item['type'] === 'file') {
                $asset = AssetMeta::where('container', $this->container)->find($item['key']);

                if ($asset) {
                    $service->duplicateFile($this->container, $asset);
                }
            } else {
                $service->duplicateFolder($this->container, $item['key']);
            }
        }

        unset($this->listing);
        Flux::toast(text: 'Duplicated.', variant: 'success');
    }

    protected function deleteItems(array $items): void
    {
        $this->authorizeItemTypes($items, "delete {$this->container} assets", "edit folders {$this->container} assets");

        $service = app(MediaService::class);

        foreach ($items as $item) {
            if ($item['type'] === 'file') {
                $asset = AssetMeta::where('container', $this->container)->find($item['key']);

                if ($asset) {
                    $service->deleteFile($this->container, $asset);
                }
            } else {
                $service->deleteFolder($this->container, $item['key']);
            }
        }

        unset($this->listing);
        Flux::toast(text: 'Deleted.', variant: 'success');
    }

    public function render()
    {
        return view('media::index');
    }
}
