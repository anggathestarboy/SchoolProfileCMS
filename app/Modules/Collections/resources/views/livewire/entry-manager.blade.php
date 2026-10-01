<section class="w-full" x-data="{ deleteEntryId: null, deleteSiblingCount: 0, deleteAllLocales: true, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M7.111 20A3.111 3.111 0 0 1 4 16.889v-12C4 4.398 4.398 4 4.889 4h4.444a.89.89 0 0 1 .89.889v12A3.111 3.111 0 0 1 7.11 20Zm0 0h12a.889.889 0 0 0 .889-.889v-4.444a.889.889 0 0 0-.889-.89h-4.389a.889.889 0 0 0-.62.253l-3.767 3.665a.933.933 0 0 0-.146.185c-.868 1.433-1.581 1.858-3.078 2.12Zm0-3.556h.009m7.933-10.927 3.143 3.143a.889.889 0 0 1 0 1.257l-7.974 7.974v-8.8l3.574-3.574a.889.889 0 0 1 1.257 0Z" />
            </svg>

            {{-- <flux:link :href="route('collections.index')" variant="subtle" wire:navigate class="text-sm">{{ __("← Collections") }}</flux:link> --}}
            <flux:heading size="xl" class="">{{ __(":collection", ["collection" => $collection->title]) }}</flux:heading>
            {{-- <flux:subheading>{{ __("Handle: :handle", ["handle" => $collection->handle]) }}</flux:subheading> --}}
        </div>

        @if ($this->availableBlueprints->isNotEmpty() && auth()->user()->hasPermission("create {$collection->handle} entries") && $this->createSite() !== '')
            <form method="GET" class="flex gap-2 items-center" action="{{ route('collections.entries.create', ['collection' => $collection->handle, 'site' => $this->createSite()]) }}" class="flex flex-col items-end gap-2">
                <flux:select name="blueprint" size="sm">
                    @foreach ($this->availableBlueprints as $availableBlueprint)
                        <option value="{{ $availableBlueprint->handle }}" @selected ($availableBlueprint->handle === $collection->effectiveBlueprint()?->handle)>{{ $availableBlueprint->handle }}</option>
                    @endforeach
                </flux:select>
                <flux:button variant="primary" size="sm" type="submit">{{ __("Create " . ":collection", ["collection" => $collection->title]) }}</flux:button>
            </form>
        @else
            <flux:button variant="primary" disabled>{{ __("New entry") }}</flux:button>
        @endif
    </div>

    <div class="space-y-5 mt-3">
        @if ($this->availableBlueprints->isEmpty())
            <flux:text class="text-zinc-500">{{ __("This collection has no blueprint linked yet — link one in the Collections module before creating entries.") }}</flux:text>
        @endif

        <div class="flex flex-wrap items-end gap-3">
            <div class="min-w-64 flex-1">
                <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by slug or title…') }}" icon="magnifying-glass" size="sm" />
            </div>

            @if ($this->availableSites->count() > 1)
                <div class="w-48">
                    <flux:select wire:model.live="siteFilter" size="sm">
                        <flux:select.option value="">{{ __("All sites") }}</flux:select.option>
                        @foreach ($this->availableSites as $availableSite)
                            <flux:select.option value="{{ $availableSite->handle }}">{{ $availableSite->name }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            @endif
        </div>
    </div>

    <div class="mt-7 mb-3">
        @if ($orderable)
            <flux:text class="text-xs text-zinc-500">{{ __("Drag the handle to reorder. The top entry becomes the site's homepage.") }}</flux:text>
        @endif
    </div>

    @if ($this->bulkActionsAvailable() && count($selected) > 0)
        <div class="flex items-end gap-2">
            <flux:text class="text-sm text-zinc-500">{{ __(":count selected", ["count" => count($selected)]) }}</flux:text>

            @if (auth() ->user() ->hasPermission("edit {$collection->handle} entries"))
                <flux:select
                    size="sm"
                    class="w-40"
                    placeholder="{{ __('Set status…') }}"
                    x-on:change="
                        $wire.bulkUpdateStatus($event.target.value);
                        $event.target.value = '';
                    "
                >
                    @foreach ($statuses as $value => $label)
                        @if ($this->canBulkUpdateStatus($value))
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>
            @endif

            @if ($this->canBulkDelete())
                <flux:modal.trigger name="confirm-bulk-delete-entries">
                    <flux:button size="sm" variant="danger" type="button">{{ __("Delete") }}</flux:button>
                </flux:modal.trigger>
            @endif
        </div>
    @endif

    @if ($collection->structured())
        <ul @if ($orderable) wire:sort="reorderRoot" @endif class="space-y-2">
            @forelse ($this->entryTree as $entry)
                @include ("collections::livewire.partials.entry-tree-row", ["entry" => $entry, "collection" => $collection, "statuses" => $statuses, "orderable" => $orderable])
            @empty
                <li class="text-sm text-zinc-500">{{ $search !== "" || $siteFilter !== "" ? __("No entries match your filters.") : __("No entries yet.") }}</li>
            @endforelse
        </ul>
    @else
        <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
            <div class="bg-white rounded-xl dark:bg-zinc-800">
                <flux:table>
                    <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                        <flux:table.column class="w-8">
                            @if ($this->bulkActionsAvailable() && $this->entries->isNotEmpty())
                                <input type="checkbox" wire:click="toggleSelectAll" :checked="count(array_intersect($selected, $this->entries->pluck('id')->all())) === $this->entries->count()" />
                            @endif
                        </flux:table.column>
                        <flux:table.column class="w-25">{{ __("Site") }}</flux:table.column>
                        <flux:table.column>{{ __("Title") }}</flux:table.column>
                        <flux:table.column>{{ __("Slug") }}</flux:table.column>
                        {{-- <flux:table.column>{{ __("Blueprint") }}</flux:table.column> --}}
                        <flux:table.column>{{ __("Updated") }}</flux:table.column>
                        <flux:table.column>{{ __("Status") }}</flux:table.column>
                        <flux:table.column class="w-32"></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows :wire:sort="$orderable ? 'reorderRoot' : null">
                        @forelse ($this->entries as $entry)
                            @php
                                $canEdit = $entry->canBeEditedBy(auth()->user());
                                $canDelete = $entry->canBeDeletedBy(auth()->user());
                                $canOperate = $canEdit || $canDelete;
                            @endphp
                            <flux:table.row wire:key="entry-{{ $entry->id }}" :wire:sort:item="$orderable ? $entry->id : null" x-on:contextmenu.prevent.stop="{{ $canOperate ? 'contextMenu = \'entry-' . $entry->id . '\'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10' : '' }}">
                                <flux:table.cell>
                                    @if ($orderable)
                                        <flux:icon.bars-3 wire:sort:handle class="size-4 shrink-0 cursor-grab text-zinc-400" />
                                    @elseif ($this->bulkActionsAvailable())
                                        <input type="checkbox" wire:model.live="selected" value="{{ $entry->id }}" />
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    @php
                                        $siteColors = [
                                            "indonesia" => "bg-red-500",
                                            "english" => "bg-blue-500",
                                        ];

                                        $color = $siteColors[strtolower($entry->site)] ?? "bg-gray-400";
                                    @endphp

                                    <div class="flex items-center gap-2">
                                        <span class="size-2 rounded-full {{ $color }}"></span>
                                        <span>{{ $entry->site }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex items-center gap-1" style="padding-inline-start: {{ ($entry->depth ?? 0) * 0.2 }}rem">
                                        @if (($entry->depth ?? 0) > 0)
                                            <flux:icon.arrow-turn-down-right class="size-3.5 shrink-0 text-zinc-400" />
                                        @endif

                                        @if ($entry->order === 1)
                                            <svg class="w-4 h-4 text-gray-800 -mt-0.5" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m4 12 8-8 8 8M6 10.5V19a1 1 0 0 0 1 1h3v-3a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v3h3a1 1 0 0 0 1-1v-8.5" />
                                            </svg>
                                        @endif

                                        @if ($canEdit)
                                            <a href="{{ route('collections.entries.edit', ['collection' => $collection->handle, 'entry' => $entry->id]) }}" wire:navigate class="hover:text-orange-700">{{ $entry->data["title"] ?? "—" }}</a>
                                        @else
                                            <span>{{ $entry->data["title"] ?? "—" }}</span>
                                        @endif

                                        @if ($entry->parentTitle ?? null)
                                            <flux:badge size="sm" color="zinc">{{ __("Parent: :title", ["title" => $entry->parentTitle]) }}</flux:badge>
                                        @endif
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>{{ $entry->slug ?? "—" }}</flux:table.cell>
                                {{-- <flux:table.cell>{{ $entry->blueprint ?? "—" }}</flux:table.cell> --}}
                                <flux:table.cell class="w-[13%]">{{ $entry->updated_at?->diffForHumans() }}</flux:table.cell>
                                <flux:table.cell class="w-[10%]">
                                    <flux:badge :color="match ($entry->status) {
        'published' => 'green',
        'scheduled' => 'blue',
        'need_review' => 'amber',
        default => 'zinc',
    }"> {{ $statuses[$entry->status] ?? $entry->status }} </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell class="flex justify-end gap-2 w-32">
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
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row>
                                <flux:table.cell colspan="7"> {{ $search !== "" || $siteFilter !== "" ? __("No entries match your filters.") : __("No entries yet.") }} </flux:table.cell>
                            </flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
    @endif

    <flux:modal name="confirm-delete-entry" class="min-w-[26rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this entry?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>

            <template x-if="deleteSiblingCount > 0">
                <flux:field variant="inline">
                    <flux:checkbox x-model="deleteAllLocales" />
                    <flux:label>{{ __("Also delete its other language versions") }}</flux:label>
                    <flux:description><span x-text="deleteSiblingCount"></span> {{ __("other entries") }}</flux:description>
                </flux:field>
            </template>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteEntryId, deleteAllLocales)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-bulk-delete-entries" class="min-w-[26rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete :count selected entries?", ["count" => count($selected)]) }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This also deletes their other language versions. This cannot be undone.") }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.bulkDelete()">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
