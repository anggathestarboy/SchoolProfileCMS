<section class="w-full space-y-6" x-data="{ deleteSiteId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15v3c0 .5523.44772 1 1 1h4v-4m-5 0v-4m0 4h5m-5-4V6c0-.55228.44772-1 1-1h16c.5523 0 1 .44772 1 1v1.98935M3 11h5v4m9.4708 4.1718-.8696-1.4388-2.8164-.235-2.573-4.2573 1.4873-2.8362 1.4441 2.3893c.3865.6396 1.2183.8447 1.8579.4582.6396-.3866.8447-1.2184.4582-1.858l-1.444-2.38925h3.1353l2.6101 4.27715-1.0713 2.5847.8695 1.4388" />
            </svg>

            <flux:heading size="xl">{{ __("Sites Manager") }}</flux:heading>
        </div>

        @if ($this->canConfigureSites())
            <flux:button variant="primary" size="sm" wire:click="create">{{ __("Add site") }}</flux:button>
        @endif
    </div>

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white rounded-xl dark:bg-zinc-800">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column>{{ __("Name") }}</flux:table.column>
                    <flux:table.column>{{ __("Handle") }}</flux:table.column>
                    <flux:table.column>{{ __("URL") }}</flux:table.column>
                    <flux:table.column>{{ __("Locale") }}</flux:table.column>
                    <flux:table.column>{{ __("Language") }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->sites as $index => $site)
                        <flux:table.row wire:key="site-{{ $site->id }}" x-on:contextmenu.prevent.stop="contextMenu = 'site-{{ $site->id }}'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10">
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <span>{{ $site->name }}</span>
                                    @if ($index === 0)
                                        <flux:badge size="sm" color="orange">{{ __("Origin") }}</flux:badge>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $site->handle }}</flux:table.cell>
                            <flux:table.cell>{{ $site->url }}</flux:table.cell>
                            <flux:table.cell>{{ $site->locale }}</flux:table.cell>
                            <flux:table.cell>{{ $site->lang }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-1">
                                @if ($this->canConfigureSites())
                                    <flux:button size="sm" variant="ghost" icon="chevron-up" wire:click="moveUp({{ $site->id }})" :disabled="$index === 0" />
                                    <flux:button size="sm" variant="ghost" icon="chevron-down" wire:click="moveDown({{ $site->id }})" :disabled="$index === $this->sites->count() - 1" />

                                    <flux:dropdown>
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            <flux:menu.item icon="cog-6-tooth" wire:click="edit({{ $site->id }})">{{ __("Settings") }}</flux:menu.item>
                                            @if ($index === 0)
                                                <flux:menu.item icon="trash" variant="danger" disabled>{{ __("Delete") }}</flux:menu.item>
                                            @else
                                                <flux:modal.trigger name="confirm-delete-site">
                                                    <flux:menu.item icon="trash" variant="danger" x-on:click="deleteSiteId = {{ $site->id }}">{{ __("Delete") }}</flux:menu.item>
                                                </flux:modal.trigger>
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>

                                    <div x-show="contextMenu === 'site-{{ $site->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                        <button type="button" wire:click.stop="edit({{ $site->id }})" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Settings") }}</button>
                                        @if ($index === 0)
                                            <button type="button" disabled class="block w-full px-3 py-1.5 text-left text-sm text-red-600 opacity-50">{{ __("Delete") }}</button>
                                        @else
                                            <flux:modal.trigger name="confirm-delete-site">
                                                <button type="button" x-on:click="deleteSiteId = {{ $site->id }}; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                                            </flux:modal.trigger>
                                        @endif
                                    </div>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="6">{{ __("No sites yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="site-form" class="max-w-2xl" :show="$errors->isNotEmpty()">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __("Edit site") : __("Add site") }}</flux:heading>

            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="name" :label="__('Name')" size="sm" placeholder="English" required autofocus />
                <div class="flex items-end gap-2">
                    <flux:input size="sm" wire:model="handle" :label="__('Handle')" placeholder="english" class="flex-1" required />
                    <flux:button size="sm" type="button" icon="arrow-path" wire:click="regenerateHandle" :tooltip="__('Regenerate from name')" />
                </div>
                <flux:input size="sm" wire:model="url" :label="__('URL')" placeholder="https://example.com/" required />
                <flux:input size="sm" wire:model="locale" :label="__('Locale')" placeholder="en_US" required />
                <flux:field class="col-span-2">
                    <flux:label>{{ __("Language") }}</flux:label>
                    <div
                        x-data="{
                            search: '',
                            positionMenu() {
                                const rect = $refs.languageTrigger.getBoundingClientRect();
                                Object.assign($refs.languageMenu.style, {
                                    top: `${rect.bottom + 8}px`,
                                    left: `${rect.left}px`,
                                    width: `${rect.width}px`,
                                });
                            },
                        }"
                    >
                        <flux:button
                            size="sm"
                            x-ref="languageTrigger"
                            type="button"
                            class="w-full justify-between!"
                            icon:trailing="chevron-down"
                            x-on:click="
                                positionMenu();
                                $refs.languageMenu.togglePopover();
                            "
                        >
                            {{ $lang !== "" ? ($this->languageOptions[$lang] ?? $lang) . " ({$lang})" : __("Select a language") }}
                        </flux:button>

                        <div
                            x-ref="languageMenu"
                            popover
                            x-on:beforetoggle="
                                if ($event.newState === 'open') {
                                    search = '';
                                    positionMenu();
                                    $nextTick(() => $refs.languageSearchInput.focus());
                                }
                            "
                            class="fixed m-0 rounded-lg border border-zinc-200 bg-white p-1.25 shadow-lg dark:border-zinc-600 dark:bg-zinc-700"
                        >
                            <div class="mb-1 border-b border-zinc-200 pb-2 dark:border-zinc-600">
                                <flux:input x-ref="languageSearchInput" x-model="search" size="sm" icon="magnifying-glass" placeholder="{{ __('Search language...') }}" autocomplete="off" class:input="outline-none" />
                            </div>

                            <div class="max-h-64 overflow-y-auto">
                                @foreach ($this->languageOptions as $code => $languageName)
                                    <button
                                        type="button"
                                        wire:click="$set('lang', '{{ $code }}')"
                                        x-on:click="$refs.languageMenu.hidePopover()"
                                        x-show="search === '' || {{ \Illuminate\Support\Js::from(strtolower("{$languageName} {$code}")) }}.includes(search.toLowerCase())"
                                        class="flex w-full items-center rounded-md px-2 py-1.5 text-start text-sm font-medium text-zinc-800 hover:bg-zinc-50 dark:text-white dark:hover:bg-zinc-600"
                                    >
                                        {{ $languageName }} ({{ $code }})
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <flux:error name="lang" />
                </flux:field>
            </div>

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:subheading>{{ __("Additional attributes") }}</flux:subheading>
                    <flux:button size="sm" type="button" wire:click="addAttribute">{{ __("Add attribute") }}</flux:button>
                </div>

                <div class="space-y-2">
                    @foreach ($customAttributes as $index => $attribute)
                        <div wire:key="attribute-{{ $index }}" class="grid grid-cols-12 gap-3">
                            <div class="col-span-5">
                                <flux:input wire:model="customAttributes.{{ $index }}.key" placeholder="native_name" size="sm" />
                            </div>
                            <div class="col-span-6">
                                <flux:input wire:model="customAttributes.{{ $index }}.value" placeholder="Deutsch" size="sm" />
                            </div>
                            <div class="col-span-1 flex items-start">
                                <flux:button size="sm" variant="ghost" icon="trash" type="button" wire:click="removeAttribute({{ $index }})" />
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete-site" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this site?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteSiteId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
