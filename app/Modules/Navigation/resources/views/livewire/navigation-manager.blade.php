<section class="w-full space-y-6" x-data="{ deleteNavigationId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 6.5h2M11 18h2m-7-5v-2m12 2v-2M5 8h2a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1Zm0 12h2a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1H5a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1Zm12 0h2a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1Zm0-12h2a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1Z" />
            </svg>

            <flux:heading size="xl">{{ __("Navigation") }}</flux:heading>
        </div>

        <flux:button variant="primary" size="sm" wire:click="create">{{ __("Add navigation") }}</flux:button>
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
                    @forelse ($this->navigations as $navigation)
                        @php
                            $canEdit = $this->canEdit($navigation);
                            $canDelete = $this->canDelete($navigation);
                            $canOperate = $canEdit || $canDelete;
                        @endphp
                        <flux:table.row wire:key="navigation-{{ $navigation->id }}" x-on:contextmenu.prevent.stop="{{ $canOperate ? 'contextMenu = \'navigation-' . $navigation->id . '\'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10' : '' }}">
                            <flux:table.cell>
                                @if ($canEdit)
                                    <a @if (!$this->isProtected($navigation) && count($navigation->effectiveBlueprint()?->data["fields"] ?? []) === 0) @else class="hover:text-orange-700" href="{{route('navigation.edit', $navigation->handle)}}" wire:navigate @endif>{{ $navigation->title }} </a>
                                @else
                                    <span>{{ $navigation->title }}</span>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $navigation->handle }}</flux:table.cell>
                            <flux:table.cell>{{ $navigation->effectiveBlueprint()?->handle ?? "—" }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-1">
                                @if ($canEdit)
                                    @if (!$this->isProtected($navigation) && count($navigation->effectiveBlueprint()?->data["fields"] ?? []) === 0)
                                        <flux:button size="sm" variant="primary" disabled tooltip="{{ __('Add fields to this navigation\'s blueprint first.') }}">{{ __("Edit content") }}</flux:button>
                                    @else
                                        <flux:button size="sm" variant="primary" :href="route('navigation.edit', $navigation->handle)" wire:navigate>{{ $this->isProtected($navigation) ? __("Edit tree") : __("Edit content") }}</flux:button>
                                    @endif
                                @endif

                                @if ($canOperate)
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        @if ($this->isProtected($navigation))
                                            <flux:menu.item icon="pencil" disabled>{{ __("Edit") }}</flux:menu.item>
                                        @elseif ($canEdit)
                                            <flux:menu.item icon="pencil" wire:click="edit({{ $navigation->id }})">{{ __("Edit") }}</flux:menu.item>
                                        @endif
                                        @if ($this->isProtected($navigation))
                                            <flux:menu.item icon="trash" variant="danger" disabled>{{ __("Delete") }}</flux:menu.item>
                                        @elseif ($canDelete)
                                            <flux:modal.trigger name="confirm-delete-navigation">
                                                <flux:menu.item icon="trash" variant="danger" x-on:click="deleteNavigationId = {{ $navigation->id }}">{{ __("Delete") }}</flux:menu.item>
                                            </flux:modal.trigger>
                                        @endif
                                    </flux:menu>
                                </flux:dropdown>
                                @endif

                                @if ($canOperate)
                                <div x-show="contextMenu === 'navigation-{{ $navigation->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    @if ($this->isProtected($navigation))
                                        <button type="button" disabled class="block w-full px-3 py-1.5 text-left text-sm opacity-50">{{ __("Edit") }}</button>
                                    @elseif ($canEdit)
                                        <button type="button" wire:click.stop="edit({{ $navigation->id }})" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Edit") }}</button>
                                    @endif
                                    @if ($this->isProtected($navigation))
                                        <button type="button" disabled class="block w-full px-3 py-1.5 text-left text-sm text-red-600 opacity-50">{{ __("Delete") }}</button>
                                    @elseif ($canDelete)
                                        <flux:modal.trigger name="confirm-delete-navigation">
                                            <button type="button" x-on:click="deleteNavigationId = {{ $navigation->id }}; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                                        </flux:modal.trigger>
                                    @endif
                                </div>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="4">
                                {{ __("No navigations yet. Run") }}
                                <code class="rounded bg-zinc-100 px-1.5 py-0.5 font-mono text-xs text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300"> php artisan admin:sidebar </code>
                                {{ __("to create the default admin navigation.") }}
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="navigation-form" class="max-w-lg" :show="$errors->isNotEmpty()">
        <form wire:submit="save" class="space-y-6">
            <flux:heading size="lg">{{ $editingId ? __("Edit navigation") : __("Add navigation") }}</flux:heading>

            <div class="space-y-4">
                <flux:input size="sm" wire:model="title" :label="__('Title')" placeholder="Main menu" required autofocus />

                <div class="flex items-end gap-2">
                    <div class="flex-1">
                        <flux:input size="sm" wire:model="handle" :label="__('Handle')" placeholder="main" class="flex-1" required />
                    </div>
                    <flux:button size="sm" type="button" icon="arrow-path" wire:click="regenerateHandle" :tooltip="__('Regenerate from title')" />
                </div>
            </div>

            @unless ($editingId)
                <flux:checkbox wire:model.live="createBlueprint" :label="__('Create a blueprint for this navigation')" :description="__('Recommended — you\'ll add its fields right after saving. Turn off to pick an existing blueprint instead.')" />
            @endunless

            @unless ($createBlueprint)
                <flux:select wire:model="blueprint" :label="__('Blueprint')" placeholder="{{ __('No blueprint') }}">
                    @foreach ($this->availableBlueprints as $availableBlueprint)
                        <flux:select.option value="{{ $availableBlueprint->handle }}"> {{ $availableBlueprint->namespace ? "{$availableBlueprint->namespace}.{$availableBlueprint->handle}" : $availableBlueprint->handle }} </flux:select.option>
                    @endforeach
                </flux:select>

                @if ($this->availableBlueprints->isEmpty())
                    <flux:text class="text-zinc-500">{{ __("No blueprints yet — create one in the Blueprints module first.") }}</flux:text>
                @endif
            @endunless

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete-navigation" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this navigation?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteNavigationId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
