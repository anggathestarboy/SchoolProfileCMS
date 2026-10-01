<section class="w-full space-y-6" x-data="{ deleteCollectionId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.03v13m0-13c-2.819-.831-4.715-1.076-8.029-1.023A.99.99 0 0 0 3 6v11c0 .563.466 1.014 1.03 1.007 3.122-.043 5.018.212 7.97 1.023m0-13c2.819-.831 4.715-1.076 8.029-1.023A.99.99 0 0 1 21 6v11c0 .563-.466 1.014-1.03 1.007-3.122-.043-5.018.212-7.97 1.023" />
            </svg>

            <flux:heading size="xl">{{ __("Collections") }}</flux:heading>
        </div>

        <flux:button variant="primary" size="sm" wire:click="create">{{ __("New collection") }}</flux:button>
    </div>

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white rounded-xl dark:bg-zinc-800">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column>{{ __("Title") }}</flux:table.column>
                    <flux:table.column>{{ __("Handle") }}</flux:table.column>
                    <flux:table.column>{{ __("Blueprint") }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->collections as $collection)
                        <flux:table.row wire:key="collection-{{ $collection->id }}" x-on:contextmenu.prevent.stop="contextMenu = 'collection-{{ $collection->id }}'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10">
                            <flux:table.cell>{{ $collection->title }}</flux:table.cell>
                            <flux:table.cell>{{ $collection->handle }}</flux:table.cell>
                            <flux:table.cell>{{ $collection->effectiveBlueprint()?->handle ?? "—" }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-2">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item icon="document-text" :href="route('collections.entries', $collection->handle)" wire:navigate>{{ __("Entries") }}</flux:menu.item>
                                        <flux:menu.item icon="cog-6-tooth" wire:click="edit({{ $collection->id }})">{{ __("Settings") }}</flux:menu.item>
                                        <flux:modal.trigger name="confirm-delete-collection">
                                            <flux:menu.item icon="trash" variant="danger" x-on:click="deleteCollectionId = {{ $collection->id }}">{{ __("Delete") }}</flux:menu.item>
                                        </flux:modal.trigger>
                                    </flux:menu>
                                </flux:dropdown>

                                <div x-show="contextMenu === 'collection-{{ $collection->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    <a href="{{ route('collections.entries', $collection->handle) }}" wire:navigate x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Entries") }}</a>
                                    <button type="button" wire:click.stop="edit({{ $collection->id }})" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Settings") }}</button>
                                    <flux:modal.trigger name="confirm-delete-collection">
                                        <button type="button" x-on:click="deleteCollectionId = {{ $collection->id }}; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                                    </flux:modal.trigger>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">{{ __("No collections yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="collection-form" class="max-w-lg" :show="$errors->isNotEmpty()">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __("Edit collection") : __("New collection") }}</flux:heading>

            <flux:input wire:model="title" :label="__('Title')" required autofocus />

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input wire:model="handle" :label="__('Handle')" placeholder="blog_posts" required />
                </div>
                <flux:button type="button" icon="arrow-path" wire:click="regenerateHandle" :tooltip="__('Regenerate from title')" />
            </div>

            @if ($this->availableSites->isNotEmpty())
                <flux:checkbox.group wire:model.live="sites" :label="__('Sites')" :description="__('Which sites is this collection available on? Leave all checked for no restriction. The default site is the origin language and can\'t be removed.')">
                    @foreach ($this->availableSites as $availableSite)
                        <flux:checkbox value="{{ $availableSite->handle }}" label="{{ $availableSite->name }}" :description="$availableSite->handle === $this->defaultSiteHandle ? __('Default — origin language') : null" :disabled="$availableSite->handle === $this->defaultSiteHandle" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            @if (count($sites) > 1)
                <flux:radio.group wire:model.live="routeMode" variant="segmented" :label="__('Route')">
                    <flux:radio value="single" :label="__('Single')" />
                    <flux:radio value="per_site" :label="__('Per-site')" />
                </flux:radio.group>
            @endif

            @php $routeVariables = __("Available variables: {slug}, {year}, {month}, {day}") . ($structured ? ", {parent_uri}" : ""); @endphp

            @if ($routeMode === "per_site" && count($sites) > 1)
                <div class="space-y-4">
                    @foreach ($this->availableSites->whereIn("handle", $sites) as $routeSite)
                        <flux:input wire:model="routes.{{ $routeSite->handle }}" :label="__(':site route', ['site' => $routeSite->name])" placeholder="blog/{slug}" :description="$routeVariables" />
                    @endforeach
                </div>
            @else
                <flux:input wire:model="route" :label="count($sites) > 1 ? null : __('Route')" placeholder="{{ $structured ? '{parent_uri}/{slug}' : 'blog/{slug}' }}" :description="$routeVariables" />
            @endif

            <div class="space-y-4 border-t border-zinc-200 pt-6 dark:border-zinc-700">
                <flux:checkbox wire:model.live="structured" :label="__('Structured')" :description="__('Let entries be nested under a parent entry, enabling the {parent_uri} route variable.')" />

                <flux:checkbox wire:model="orderable" :label="__('Orderable')" :description="__('Let entries be manually reordered by drag-and-drop.')" />

                <flux:checkbox wire:model="visibleOrder" :label="__('Visible Order')" :description="__('Show the Order field in the entry form sidebar. Ordering still works even when hidden.')" />

                <flux:checkbox wire:model="livePreview" :label="__('Live Preview')" :description="__('Let editors preview an entry on the live site as they edit it, even before it\'s published.')" />

                <flux:checkbox wire:model="schemaEnabled" :label="__('Schema')" :description="__('Add a Schema tab to this collection\'s entries for attaching schema.org JSON-LD blocks (Article, Organization, etc.) to individual entries.')" />

                <flux:checkbox wire:model="seoEnabled" :label="__('SEO')" :description="__('Add an SEO tab to this collection\'s entries for meta title/description, robots, sitemap hints, etc.')" />

                @if ($structured)
                    <flux:input type="number" min="1" wire:model="maxDepth" :label="__('Max depth')" placeholder="{{ __('No limit') }}" />

                    <flux:checkbox wire:model="expectsRoot" :label="__('Expect a root page')" :description="__('Require exactly one top-level entry (e.g. a homepage) instead of allowing several.')" />
                @endif
            </div>

            <flux:radio.group wire:model="saveAction" :label="__('Action after save')" :description="__('Where the entry form goes after saving one of this collection\'s entries.')">
                <flux:radio value="listing" :label="__('Go to listing')" />
                <flux:radio value="editing" :label="__('Continue editing')" />
                <flux:radio value="create_another" :label="__('Create another')" />
            </flux:radio.group>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete-collection" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this collection?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("Collections with existing entries can't be deleted — delete those first. This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteCollectionId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
