<div x-data="{}">
    <flux:modal name="asset-picker" class="max-w-4xl space-y-6">
        <div class="pr-8">
            <flux:heading size="lg">{{ __('Select assets') }}</flux:heading>
        </div>

        @if ($this->selectedAssets->isNotEmpty())
            <div class="space-y-1.5 rounded-lg border border-zinc-200 p-2 dark:border-zinc-700">
                <flux:text class="text-xs font-medium text-zinc-500">{{ __('Selected — counts toward the limit even from other folders') }}</flux:text>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->selectedAssets as $asset)
                        <button type="button" wire:click="toggle({{ $asset->id }})" wire:key="picked-{{ $asset->id }}" title="{{ $asset->basename }}" class="group flex w-16 shrink-0 flex-col gap-1">
                            <div class="relative size-16 overflow-hidden rounded border-2 border-blue-500">
                                <div class="flex h-full w-full items-center justify-center overflow-hidden bg-zinc-100 dark:bg-zinc-800">
                                    @include('media::partials._thumbnail', ['asset' => $asset])
                                </div>
                                <span class="absolute inset-0 flex items-center justify-center bg-black/50 text-white opacity-0 group-hover:opacity-100">✕</span>
                            </div>
                            <span class="w-full truncate text-center text-[11px] text-zinc-500">{{ $asset->basename }}</span>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            @if ($search === '')
                <flux:breadcrumbs>
                    <flux:breadcrumbs.item class="cursor-pointer" wire:click="goToFolder('')" :current="$folder === ''">{{ __('Home') }}</flux:breadcrumbs.item>
                    @foreach ($this->breadcrumbs as $crumb)
                        <flux:breadcrumbs.item class="cursor-pointer" wire:click="goToFolder('{{ $crumb['path'] }}')" :current="$crumb['path'] === $folder">{{ $crumb['name'] }}</flux:breadcrumbs.item>
                    @endforeach
                </flux:breadcrumbs>
            @else
                <div></div>
            @endif

            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" placeholder="{{ __('Search...') }}" size="sm" class="w-full sm:w-64" />
        </div>

        <div class="max-h-96 overflow-y-auto px-1">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-7">
                @foreach ($this->listing['folders'] as $folderItem)
                    <button type="button" wire:click="goToFolder('{{ $folderItem['path'] }}')" class="group flex flex-col items-center gap-1 rounded-lg p-1">
                        <div class="flex aspect-square w-full items-center justify-center rounded-lg p-2 hover:bg-zinc-100 dark:hover:bg-zinc-800">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 80 66" class="size-full text-(--color-accent)/90 group-hover:text-(--color-accent)">
                                <path fill="currentColor" d="M73.684 8.8h-40a2.1 2.1 0 0 1-1.516-.66L26.526 2.2A6.4 6.4 0 0 0 24.467.616 6.2 6.2 0 0 0 21.98 0H7.537C5.538 0 3.62.83 2.207 2.307.794 3.784 0 5.787 0 7.876v50.248c0 2.089.794 4.092 2.207 5.57C3.621 65.17 5.538 66 7.537 66h64.926c2 0 3.916-.83 5.33-2.307C79.206 62.216 80 60.213 80 58.124V15.4c0-1.75-.665-3.43-1.85-4.667C76.966 9.495 75.36 8.8 73.684 8.8"></path>
                            </svg>
                        </div>
                        <span class="w-full truncate text-center text-xs">{{ $folderItem['name'] }}</span>
                    </button>
                @endforeach

                @foreach ($this->listing['files'] as $asset)
                    <button
                        type="button"
                        wire:click="toggle({{ $asset->id }})"
                        wire:key="asset-{{ $asset->id }}"
                        @unless ($this->isAssetAllowed($asset)) disabled title="{{ __('This file type is not allowed for this field.') }}" @endunless
                        class="relative flex flex-col gap-1 rounded-lg border p-1.5 text-left {{ $this->isSelected($asset->id) ? 'border-blue-500 ring-2 ring-blue-500' : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-700 dark:hover:bg-zinc-800' }} {{ $this->isAssetAllowed($asset) ? '' : 'cursor-not-allowed opacity-40' }}"
                    >
                        <div class="flex aspect-square w-full items-center justify-center overflow-hidden rounded bg-zinc-100 dark:bg-zinc-800">
                            @include('media::partials._thumbnail', ['asset' => $asset])
                        </div>
                        <span class="w-full truncate text-center text-xs">{{ $asset->basename }}</span>
                        @if ($this->isSelected($asset->id))
                            <flux:icon.check-circle class="absolute right-1 top-1 size-4 rounded-full bg-white text-blue-500 dark:bg-zinc-900" />
                        @endif
                    </button>
                @endforeach

                @if ($this->listing['folders']->isEmpty() && $this->listing['files']->isEmpty())
                    <div class="col-span-full py-12 text-center text-zinc-400">{{ __('Nothing here yet.') }}</div>
                @endif
            </div>
        </div>

        @error('uploads.*')
            <flux:text class="text-red-500">{{ $message }}</flux:text>
        @enderror

        <div class="flex items-center justify-between border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <div>
                <input type="file" class="hidden" x-ref="assetPickerFileInput" multiple wire:model="uploads" />
                <flux:button type="button" size="sm" icon="arrow-up-tray" x-on:click="$refs.assetPickerFileInput.click()">{{ __('Upload') }}</flux:button>
            </div>

            <div class="flex items-center gap-2">
                <flux:text class="text-sm text-zinc-500">{{ count($selectedIds) }} {{ __('selected') }}</flux:text>
                <flux:modal.close>
                    <flux:button size="sm" variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button size="sm" variant="primary" wire:click="confirm">{{ __('Use selected') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
