<div class="space-y-6" x-data="{ contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" x-on:keydown.window.ctrl.s.prevent="$wire.save()" x-on:keydown.window.meta.s.prevent="$wire.save()" @click="contextMenu = null">
    <div class="flex items-center justify-between gap-2 border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M6 4v10m0 0a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m0 0v2m6-16v2m0 0a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m0 0v10m6-16v10m0 0a2 2 0 1 0 0 4m0-4a2 2 0 1 1 0 4m0 0v2" />
            </svg>

            <flux:heading size="xl">{{ $navigation->title }}</flux:heading>
        </div>

        <div class="flex gap-2">
            @if ($this->canEdit())
                <flux:button size="sm" wire:click="addRoot">{{ __("Add item") }}</flux:button>
                <flux:button size="sm" variant="primary" wire:click="save">{{ __("Save") }}</flux:button>
            @endif
        </div>
    </div>

    <section class="mx-auto mt-3 w-full space-y-4 rounded-lg border border-gray-200 bg-white p-4 sm:p-6 dark:border-gray-700 dark:bg-zinc-900">
        <ul wire:sort="sortRoot" class="space-y-2">
            @forelse ($tree as $i => $item)
                <li wire:key="item-{{ $item['id'] }}-{{ $i }}" wire:sort:item="{{ $item['id'] }}" class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                    <div class="flex flex-wrap items-center gap-2 bg-zinc-50 px-3 py-1.5 dark:bg-zinc-800">
                        <flux:icon.bars-3 wire:sort:handle class="size-4 shrink-0 cursor-grab text-zinc-400" />

                        <button type="button" wire:click="toggle('{{ $item['id'] }}')" class="flex min-w-0 flex-1 basis-full cursor-pointer items-center gap-2 text-left font-medium sm:basis-auto">
                            <span class="truncate">{{ $item["title"] !== "" ? $item["title"] : __("(untitled)") }}</span>
                            @if ($navigation->handle === "sidebar-admin" && !in_array($item["id"], $expanded))
                                @foreach ($item["roles"] ?? [] as $roleHandle)
                                    <flux:badge size="sm" color="zinc">{{ $this->availableRoles->firstWhere("handle", $roleHandle)?->title ?? $roleHandle }}</flux:badge>
                                @endforeach
                            @endif
                        </button>

                        @if ($this->canEdit())
                            <div class="relative flex flex-wrap items-center gap-1">
                                <flux:button size="xs" wire:click="addChild('{{ $item['id'] }}')">{{ __("Add child") }}</flux:button>

                                <button type="button" x-on:click.stop="contextMenu = contextMenu === 'item-{{ $item['id'] }}' ? null : 'item-{{ $item['id'] }}'; contextMenuX = $event.clientX - 170; contextMenuY = $event.clientY + 10" class="rounded p-1.5 hover:bg-zinc-200 dark:hover:bg-zinc-700">
                                    <flux:icon.ellipsis-horizontal class="size-4" />
                                </button>

                                <div x-show="contextMenu === 'item-{{ $item['id'] }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                <button type="button" wire:click.stop="duplicateItem('{{ $item['id'] }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Duplicate item") }}</button>
                                <button type="button" wire:click.stop="askMove('{{ $item['id'] }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Move to...") }}</button>
                                <button type="button" wire:click.stop="askDelete('{{ $item['id'] }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete item") }}</button>
                            </div>
                        </div>
                        @endif
                    </div>

                    @if (in_array($item["id"], $expanded))
                        <div class="space-y-4 bg-white p-4 dark:bg-zinc-900">
                            <flux:input wire:model="tree.{{ $i }}.title" :label="__('Title')" placeholder="About us" />

                            <flux:select wire:model.live="tree.{{ $i }}.type" :label="__('Link type')">
                                <flux:select.option value="internal">{{ __("Internal path") }}</flux:select.option>
                                <flux:select.option value="external">{{ __("External URL") }}</flux:select.option>
                                <flux:select.option value="page">{{ __("Page") }}</flux:select.option>
                            </flux:select>

                            @if ($item["type"] === "page")
                                <flux:select wire:model="tree.{{ $i }}.page_id" :label="__('Page')">
                                    <flux:select.option value="">{{ __("Select a page") }}</flux:select.option>
                                    @foreach ($this->pages as $page)
                                        <flux:select.option value="{{ $page->id }}">{{ $page->data["title"] ?? $page->slug }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                            @else
                                <flux:input wire:model="tree.{{ $i }}.url" :label="__('URL')" :placeholder="$item['type'] === 'external' ? 'https://example.com' : '/contact'" />
                            @endif

                            <flux:select wire:model="tree.{{ $i }}.target" :label="__('Open in')">
                                <flux:select.option value="_self">{{ __("Same tab") }}</flux:select.option>
                                <flux:select.option value="_blank">{{ __("New tab") }}</flux:select.option>
                            </flux:select>

                            @if ($navigation->handle === "sidebar-admin")
                                <flux:field>
                                    <flux:label>{{ __("Visible to") }}</flux:label>
                                    <flux:description>{{ __("Which roles see this link in their sidebar. A Super Admin always sees everything.") }}</flux:description>
                                    <flux:checkbox.group wire:model="tree.{{ $i }}.roles">
                                        @foreach ($this->availableRoles as $role)
                                            <flux:checkbox value="{{ $role->handle }}" label="{{ $role->title }}" />
                                        @endforeach
                                    </flux:checkbox.group>
                                </flux:field>
                            @endif
                        </div>

                        @if ($item["children"] !== [])
                            <ul wire:sort="sortChildren" wire:sort:group-id="{{ $item['id'] }}" class="space-y-2 border-t border-zinc-200 bg-white p-3 pl-4 sm:pl-9 dark:border-zinc-700 dark:bg-zinc-900">
                                @foreach ($item["children"] as $j => $child)
                                    <li wire:key="item-{{ $child['id'] }}-{{ $i }}-{{ $j }}" wire:sort:item="{{ $child['id'] }}" class="overflow-hidden rounded-lg border border-zinc-200 dark:border-zinc-700">
                                        <div class="flex flex-wrap items-center gap-2 bg-zinc-50 px-3 dark:bg-zinc-800">
                                            <flux:icon.bars-3 wire:sort:handle class="size-4 shrink-0 cursor-grab text-zinc-400" />

                                            <button type="button" wire:click="toggle('{{ $child['id'] }}')" class="flex min-w-0 flex-1 basis-full cursor-pointer items-center gap-2 text-left font-medium sm:basis-auto">
                                                <span class="truncate">{{ $child["title"] !== "" ? $child["title"] : __("(untitled)") }}</span>
                                                @if ($navigation->handle === "sidebar-admin" && !in_array($child["id"], $expanded))
                                                    @foreach ($child["roles"] ?? [] as $roleHandle)
                                                        <flux:badge size="sm" color="zinc">{{ $this->availableRoles->firstWhere("handle", $roleHandle)?->title ?? $roleHandle }}</flux:badge>
                                                    @endforeach
                                                @endif
                                            </button>

                                            @if ($this->canEdit())
                                            <div class="relative flex flex-wrap items-center gap-1">
                                                <button type="button" x-on:click.stop="contextMenu = contextMenu === 'item-{{ $child['id'] }}' ? null : 'item-{{ $child['id'] }}'; contextMenuX = $event.clientX - 170; contextMenuY = $event.clientY + 10" class="rounded p-1.5 hover:bg-zinc-200 dark:hover:bg-zinc-700">
                                                    <flux:icon.ellipsis-horizontal class="size-4" />
                                                </button>

                                                <div x-show="contextMenu === 'item-{{ $child['id'] }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                                    <button type="button" wire:click.stop="duplicateItem('{{ $child['id'] }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Duplicate item") }}</button>
                                                    <button type="button" wire:click.stop="askMove('{{ $child['id'] }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Move to...") }}</button>
                                                    <button type="button" wire:click.stop="askDelete('{{ $child['id'] }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete item") }}</button>
                                                </div>
                                            </div>
                                            @endif
                                        </div>

                                        @if (in_array($child["id"], $expanded))
                                            <div class="space-y-4 bg-white p-4 dark:bg-zinc-900">
                                                <flux:input wire:model="tree.{{ $i }}.children.{{ $j }}.title" :label="__('Title')" placeholder="Sub page" />

                                                <flux:select wire:model.live="tree.{{ $i }}.children.{{ $j }}.type" :label="__('Link type')">
                                                    <flux:select.option value="internal">{{ __("Internal path") }}</flux:select.option>
                                                    <flux:select.option value="external">{{ __("External URL") }}</flux:select.option>
                                                    <flux:select.option value="page">{{ __("Page") }}</flux:select.option>
                                                </flux:select>

                                                @if ($child["type"] === "page")
                                                    <flux:select wire:model="tree.{{ $i }}.children.{{ $j }}.page_id" :label="__('Page')">
                                                        <flux:select.option value="">{{ __("Select a page") }}</flux:select.option>
                                                        @foreach ($this->pages as $page)
                                                            <flux:select.option value="{{ $page->id }}">{{ $page->data["title"] ?? $page->slug }}</flux:select.option>
                                                        @endforeach
                                                    </flux:select>
                                                @else
                                                    <flux:input wire:model="tree.{{ $i }}.children.{{ $j }}.url" :label="__('URL')" :placeholder="$child['type'] === 'external' ? 'https://example.com' : '/contact'" />
                                                @endif

                                                <flux:select wire:model="tree.{{ $i }}.children.{{ $j }}.target" :label="__('Open in')">
                                                    <flux:select.option value="_self">{{ __("Same tab") }}</flux:select.option>
                                                    <flux:select.option value="_blank">{{ __("New tab") }}</flux:select.option>
                                                </flux:select>

                                                @if ($navigation->handle === "sidebar-admin")
                                                    <flux:field>
                                                        <flux:label>{{ __("Visible to") }}</flux:label>
                                                        <flux:description>{{ __("Which roles see this link in their sidebar. A Super Admin always sees everything.") }}</flux:description>
                                                        <flux:checkbox.group wire:model="tree.{{ $i }}.children.{{ $j }}.roles">
                                                            @foreach ($this->availableRoles as $role)
                                                                <flux:checkbox value="{{ $role->handle }}" label="{{ $role->title }}" />
                                                            @endforeach
                                                        </flux:checkbox.group>
                                                    </flux:field>
                                                @endif
                                            </div>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    @endif
                </li>
            @empty
                <li class="text-zinc-500">{{ __("No menu items yet.") }}</li>
            @endforelse
        </ul>
    </section>

    <flux:modal name="confirm-delete-item" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this item?") }}</flux:heading>
            <flux:text>{{ __("This will also delete any children under it.") }}</flux:text>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:button variant="danger" wire:click="confirmDelete">{{ __("Delete") }}</flux:button>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="move-item" class="max-w-sm">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Move item") }}</flux:heading>

            <flux:select wire:model.live="moveTargetParentId" :label="__('Move under')">
                <flux:select.option value="">{{ __("Level 1 (no parent)") }}</flux:select.option>
                @foreach ($this->moveTargets as $target)
                    <flux:select.option value="{{ $target['id'] }}">{{ $target["title"] !== "" ? $target["title"] : __("(untitled)") }}</flux:select.option>
                @endforeach
            </flux:select>

            @if ($moveTargetParentId !== "" && $this->movingItemHasChildren())
                <flux:select wire:model="moveChildrenStrategy" :label="__('This item has children. What should happen to them?')">
                    <flux:select.option value="">{{ __("Choose an option") }}</flux:select.option>
                    <flux:select.option value="promote">{{ __("Move them to level 1") }}</flux:select.option>
                    <flux:select.option value="delete">{{ __("Delete them") }}</flux:select.option>
                </flux:select>
            @endif

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" wire:click="confirmMove">{{ __("Move") }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
