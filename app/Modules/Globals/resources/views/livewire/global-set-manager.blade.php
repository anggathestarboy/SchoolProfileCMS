<section class="w-full space-y-6" x-data="{ deleteGlobalSetId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.37 7.657c2.063.528 2.396 2.806 3.202 3.87 1.07 1.413 2.075 1.228 3.192 2.644 1.805 2.289 1.312 5.705 1.312 6.705M20 15h-1a4 4 0 0 0-4 4v1M8.587 3.992c0 .822.112 1.886 1.515 2.58 1.402.693 2.918.351 2.918 2.334 0 .276 0 2.008 1.972 2.008 2.026.031 2.026-1.678 2.026-2.008 0-.65.527-.9 1.177-.9H20M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>

            <flux:heading size="xl">{{ __("Globals") }}</flux:heading>
        </div>

        <flux:button variant="primary" size="sm" wire:click="create">{{ __("New global set") }}</flux:button>
    </div>

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white rounded-xl dark:bg-zinc-800">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column>{{ __("Title") }}</flux:table.column>
                    <flux:table.column>{{ __("Handle") }}</flux:table.column>
                    {{-- <flux:table.column>{{ __("Blueprint") }}</flux:table.column> --}}
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->globalSets as $globalSet)
                        @php
                            $canEdit = $this->canEdit($globalSet);
                            $canDelete = $this->canDelete($globalSet);
                            $canOperate = $canEdit || $canDelete;
                        @endphp
                        <flux:table.row wire:key="global-set-{{ $globalSet->id }}" x-on:contextmenu.prevent.stop="{{ $canOperate ? 'contextMenu = \'global-set-' . $globalSet->id . '\'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10' : '' }}">
                            <flux:table.cell>
                                @if ($canEdit)
                                    <a href="{{ route('globals.edit', $globalSet->handle) }}" class="hover:text-orange-700" wire:navigate>{{ $globalSet->title }}</a>
                                @else
                                    <span>{{ $globalSet->title }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $globalSet->handle }}</flux:table.cell>
                            {{-- <flux:table.cell>{{ $globalSet->effectiveBlueprint()?->handle ?? "—" }}</flux:table.cell> --}}
                            <flux:table.cell class="flex justify-end gap-2">
                                @if ($canOperate)
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        @if ($canEdit)
                                            <flux:menu.item icon="pencil" :href="route('globals.edit', $globalSet->handle)" wire:navigate>{{ __("Edit values") }}</flux:menu.item>
                                            <flux:menu.item icon="cog-6-tooth" wire:click="edit({{ $globalSet->id }})">{{ __("Settings") }}</flux:menu.item>
                                        @endif
                                        @if ($canDelete)
                                            <flux:modal.trigger name="confirm-delete-global-set">
                                                <flux:menu.item icon="trash" variant="danger" x-on:click="deleteGlobalSetId = {{ $globalSet->id }}">{{ __("Delete") }}</flux:menu.item>
                                            </flux:modal.trigger>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                                @endif

                                @if ($canOperate)
                                <div x-show="contextMenu === 'global-set-{{ $globalSet->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    @if ($canEdit)
                                        <a href="{{ route('globals.edit', $globalSet->handle) }}" wire:navigate x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Edit values") }}</a>
                                        <button type="button" wire:click.stop="edit({{ $globalSet->id }})" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Settings") }}</button>
                                    @endif
                                    @if ($canDelete)
                                        <flux:modal.trigger name="confirm-delete-global-set">
                                            <button type="button" x-on:click="deleteGlobalSetId = {{ $globalSet->id }}; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                                        </flux:modal.trigger>
                                    @endif
                                </div>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">{{ __("No global sets yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="global-set-form" class="max-w-lg" :show="$errors->isNotEmpty()">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __("Edit global set") : __("New global set") }}</flux:heading>

            <flux:input wire:model="title" size="sm" :label="__('Title')" required autofocus />

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input wire:model="handle" size="sm" :label="__('Handle')" class="flex-1" required />
                </div>
                <flux:button size="sm" type="button" icon="arrow-path" wire:click="regenerateHandle" :tooltip="__('Regenerate from title')" />
            </div>

            @unless ($createBlueprint)
                <flux:select size="sm" wire:model="blueprint" :label="__('Blueprint')" placeholder="{{ __('No blueprint') }}">
                    @foreach ($this->availableBlueprints as $availableBlueprint)
                        <flux:select.option value="{{ $availableBlueprint->handle }}"> {{ $availableBlueprint->namespace ? "{$availableBlueprint->namespace}.{$availableBlueprint->handle}" : $availableBlueprint->handle }} </flux:select.option>
                    @endforeach
                </flux:select>

                @if ($this->availableBlueprints->isEmpty())
                    <flux:text class="text-zinc-500">{{ __("No blueprints yet — create one in the Blueprints module first.") }}</flux:text>
                @endif
            @endunless

            @unless ($editingId)
                <flux:checkbox wire:model.live="createBlueprint" :label="__('Create a blueprint for this global set')" :description="__('Recommended — you\'ll add its fields right after saving. Turn off to pick an existing blueprint instead.')" />
            @endunless

            @if ($this->availableSites->isNotEmpty())
                <flux:checkbox.group wire:model="sites" :label="__('Languages')" :description="__('Which sites does this global set have values for? The default site is the origin language and is always included.')">
                    @foreach ($this->availableSites as $availableSite)
                        <flux:checkbox value="{{ $availableSite->handle }}" label="{{ $availableSite->name }}" :description="$availableSite->handle === $this->defaultSiteHandle ? __('Default — origin language') : null" :disabled="$availableSite->handle === $this->defaultSiteHandle" />
                    @endforeach
                </flux:checkbox.group>
            @endif

            <flux:radio.group wire:model="saveAction" :label="__('Action after save')" :description="__('Where the values form goes after saving this global set\'s values.')">
                <flux:radio value="listing" :label="__('Go to listing')" />
                <flux:radio value="editing" :label="__('Continue editing')" />
            </flux:radio.group>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete-global-set" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this global set?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("Its values will be deleted too. This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteGlobalSetId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
