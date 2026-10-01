@php
    $siteColors = ["indonesia" => "bg-red-500", "english" => "bg-blue-500"];
    $color = $siteColors[strtolower($entry->site)] ?? "bg-gray-400";
    $canEdit = $entry->canBeEditedBy(auth()->user());
    $canDelete = $entry->canBeDeletedBy(auth()->user());
    $canOperate = $canEdit || $canDelete;
@endphp

<li wire:key="entry-{{ $entry->id }}" @if ($orderable) wire:sort:item="{{ $entry->id }}" @endif x-on:contextmenu.prevent.stop="{{ $canOperate ? 'contextMenu = \'entry-' . $entry->id . '\'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10' : '' }}" class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
    <div class="flex flex-wrap items-center gap-3 bg-white p-1 dark:bg-zinc-900">
        <div class="flex flex-col gap-2 items-center">
            <span class="size-2 shrink-0 rounded-full {{ $color }}"></span>
            @if ($orderable)
                <flux:icon.bars-3 wire:sort:handle class="size-4 shrink-0 cursor-grab text-zinc-400" />
            @endif
        </div>

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-1.5 group w-max">
                @if ($entry->order === 1)
                    <svg class="w-3.5 h-3.5 text-gray-800 dark:text-zinc-400" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                    </svg>
                @endif
                @if ($canEdit)
                    <a href="{{route('collections.entries.edit', ['collection' => $collection->handle, 'entry' => $entry->id])}}" class="font-medium text-md w-max group-hover:text-orange-700" wire:navigate>{{ $entry->data["title"] ?? "—" }}</a>
                @else
                    <span class="font-medium text-md w-max">{{ $entry->data["title"] ?? "—" }}</span>
                @endif
            </div>
            <div class="text-xs text-zinc-500 dark:text-zinc-400 flex gap-1 mt-1">
                <span class="flex gap-1 items-center border border-zinc-300 dark:border-zinc-500 rounded-full px-1.5">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.213 9.787a3.391 3.391 0 0 0-4.795 0l-3.425 3.426a3.39 3.39 0 0 0 4.795 4.794l.321-.304m-.321-4.49a3.39 3.39 0 0 0 4.795 0l3.424-3.426a3.39 3.39 0 0 0-4.794-4.795l-1.028.961" />
                    </svg>

                    {{ $entry->slug ?? "—" }}
                </span>
                <span class="flex gap-1 items-center border border-zinc-300 dark:border-zinc-500 rounded-full px-1.5">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 19V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v13H7a2 2 0 0 0-2 2Zm0 0a2 2 0 0 0 2 2h12M9 3v14m7 0v4" />
                    </svg>

                    {{ $entry->blueprint ?? "—" }}
                </span>
                <span class="flex gap-1 items-center border border-zinc-300 dark:border-zinc-500 rounded-full px-1.5">
                    <svg class="w-3 h-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>

                    {{ $entry->updated_at?->diffForHumans() }}
                </span>
            </div>
        </div>

        <div class="flex shrink-0 gap-2">
            <flux:badge size="sm" :color="match ($entry->status) {
        'published' => 'green',
        'scheduled' => 'blue',
        'need_review' => 'amber',
        default => 'zinc',
    }">{{ $statuses[$entry->status] ?? $entry->status }}</flux:badge>
            @if ($canOperate)
                <flux:dropdown>
                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                    <flux:menu>
                        @if ($canEdit)
                            <flux:menu.item icon="pencil" :href="route('collections.entries.edit', ['collection' => $collection->handle, 'entry' => $entry->id])" wire:navigate>{{ __("Edit") }}</flux:menu.item>
                        @endif
                        @if ($canDelete)
                            <flux:modal.trigger name="confirm-delete-entry">
                                <flux:menu.item icon="trash" variant="danger" x-on:click="deleteEntryId = '{{ $entry->id }}'; deleteSiblingCount = {{ $this->localeSiblingCount($entry) }}; deleteAllLocales = true">{{ __("Delete") }}</flux:menu.item>
                            </flux:modal.trigger>
                        @endif
                    </flux:menu>
                </flux:dropdown>
            @endif
        </div>

        @if ($canOperate)
            <div x-show="contextMenu === 'entry-{{ $entry->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                @if ($canEdit)
                    <a href="{{ route('collections.entries.edit', ['collection' => $collection->handle, 'entry' => $entry->id]) }}" wire:navigate x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Edit") }}</a>
                @endif
                @if ($canDelete)
                    <flux:modal.trigger name="confirm-delete-entry">
                        <button type="button" x-on:click="deleteEntryId = '{{ $entry->id }}'; deleteSiblingCount = {{ $this->localeSiblingCount($entry) }}; deleteAllLocales = true; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                    </flux:modal.trigger>
                @endif
            </div>
        @endif
    </div>

    @if ($entry->children->isNotEmpty())
        <ul @if ($orderable) wire:sort="reorderChildren" wire:sort:group-id="{{ $entry->id }}" @endif class="space-y-1.5 border-t border-zinc-200 bg-gray-100 p-1.5 dark:border-zinc-700 dark:bg-zinc-950">
            @foreach ($entry->children as $child)
                @include ("collections::livewire.partials.entry-tree-row", ["entry" => $child, "collection" => $collection, "statuses" => $statuses, "orderable" => $orderable])
            @endforeach
        </ul>
    @endif
</li>
