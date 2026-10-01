<section class="w-full" x-data="{ deleteTermId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.583 8.445h.01M10.86 19.71l-6.573-6.63a.993.993 0 0 1 0-1.4l7.329-7.394A.98.98 0 0 1 12.31 4l5.734.007A1.968 1.968 0 0 1 20 5.983v5.5a.992.992 0 0 1-.316.727l-7.44 7.5a.974.974 0 0 1-1.384.001Z" />
            </svg>
            <flux:heading size="xl">{{ __(":taxonomy", ["taxonomy" => $taxonomy->title]) }}</flux:heading>
        </div>

        @if ($this->availableBlueprints->isNotEmpty() && auth()->user()->hasPermission("create {$taxonomy->handle} terms") && $this->createSite() !== '')
            <flux:button size="sm" variant="primary" :href="route('taxonomies.terms.create', ['taxonomy' => $taxonomy->handle, 'site' => $this->createSite(), 'blueprint' => $taxonomy->effectiveBlueprint()?->handle])" wire:navigate>{{ __("New term") }}</flux:button>
        @else
            <flux:button variant="primary" size="sm" disabled>{{ __("New term") }}</flux:button>
        @endif
    </div>

    @if ($this->availableBlueprints->isEmpty())
        <flux:text class="text-zinc-500">{{ __("This taxonomy has no blueprint linked yet — link one in the Taxonomies module before creating terms.") }}</flux:text>
    @endif

    <div class="flex flex-wrap items-end gap-3 mt-3 mb-7">
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

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white rounded-xl dark:bg-zinc-800">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column class="w-25">{{ __("Site") }}</flux:table.column>
                    <flux:table.column>{{ __("Title") }}</flux:table.column>
                    <flux:table.column>{{ __("Slug") }}</flux:table.column>
                    {{-- <flux:table.column>{{ __("Blueprint") }}</flux:table.column> --}}
                    <flux:table.column>{{ __("Updated") }}</flux:table.column>
                    <flux:table.column class="w-32"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->terms as $term)
                        @php
                            $canEdit = auth()->user()->hasPermission("edit {$taxonomy->handle} terms");
                            $canDelete = auth()->user()->hasPermission("delete {$taxonomy->handle} terms");
                            $canOperate = $canEdit || $canDelete;
                        @endphp
                        <flux:table.row wire:key="term-{{ $term->id }}" x-on:contextmenu.prevent.stop="{{ $canOperate ? 'contextMenu = \'term-' . $term->id . '\'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10' : '' }}">
                            <flux:table.cell class="w-[13%]">
                                @php
                                    $siteColors = [
                                        "indonesia" => "bg-red-500",
                                        "english" => "bg-blue-500",
                                    ];

                                    $color = $siteColors[strtolower($term->site)] ?? "bg-gray-400";
                                @endphp

                                <div class="flex items-center gap-2">
                                    <span class="size-2 rounded-full {{ $color }}"></span>
                                    <span>{{ $term->site }}</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($canEdit)
                                    <a href="{{ route('taxonomies.terms.edit', ['taxonomy' => $taxonomy->handle, 'term' => $term->id]) }}" class="hover:text-orange-700" wire:navigate>{{ $term->data["title"] ?? "—" }}</a>
                                @else
                                    <span>{{ $term->data["title"] ?? "—" }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $term->slug ?? "—" }}</flux:table.cell>
                            {{-- <flux:table.cell>{{ $term->blueprint ?? "—" }}</flux:table.cell> --}}
                            <flux:table.cell class="w-[10%]">{{ $term->updated_at?->diffForHumans() }}</flux:table.cell>
                            <flux:table.cell class="flex w-32 justify-end gap-2">
                                @if ($canOperate)
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        @if ($canEdit)
                                            <flux:menu.item icon="pencil" :href="route('taxonomies.terms.edit', ['taxonomy' => $taxonomy->handle, 'term' => $term->id])" wire:navigate>{{ __("Edit") }}</flux:menu.item>
                                        @endif
                                        @if ($canDelete)
                                            <flux:modal.trigger name="confirm-delete-term">
                                                <flux:menu.item icon="trash" variant="danger" x-on:click="deleteTermId = {{ $term->id }}">{{ __("Delete") }}</flux:menu.item>
                                            </flux:modal.trigger>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                            @endif

                            @if ($canOperate)
                                <div x-show="contextMenu === 'term-{{ $term->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    @if ($canEdit)
                                        <a href="{{ route('taxonomies.terms.edit', ['taxonomy' => $taxonomy->handle, 'term' => $term->id]) }}" wire:navigate x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Edit") }}</a>
                                    @endif
                                    @if ($canDelete)
                                        <flux:modal.trigger name="confirm-delete-term">
                                            <button type="button" x-on:click="deleteTermId = {{ $term->id }}; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                                        </flux:modal.trigger>
                                    @endif
                                </div>
                            @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ $search !== "" || $siteFilter !== "" ? __("No terms match your filters.") : __("No terms yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="confirm-delete-term" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this term?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteTermId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
