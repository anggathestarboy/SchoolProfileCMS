<section class="w-full space-y-6" x-data="{ deleteGroupId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-width="2" d="M4.5 17H4a1 1 0 0 1-1-1 3 3 0 0 1 3-3h1m0-3.05A2.5 2.5 0 1 1 9 5.5M19.5 17h.5a1 1 0 0 0 1-1 3 3 0 0 0-3-3h-1m0-3.05a2.5 2.5 0 1 0-2-4.45m.5 13.5h-7a1 1 0 0 1-1-1 3 3 0 0 1 3-3h3a3 3 0 0 1 3 3 1 1 0 0 1-1 1Zm-1-9.5a2.5 2.5 0 1 1-5 0 2.5 2.5 0 0 1 5 0Z" />
            </svg>

            <flux:heading size="xl">{{ __("User Groups") }}</flux:heading>
        </div>

        <flux:button variant="primary" size="sm" wire:click="create">{{ __("Add group") }}</flux:button>
    </div>

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white dark:bg-zinc-800 rounded-xl">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column>{{ __("Title") }}</flux:table.column>
                    <flux:table.column>{{ __("Handle") }}</flux:table.column>
                    <flux:table.column>{{ __("Roles") }}</flux:table.column>
                    <flux:table.column>{{ __("Users") }}</flux:table.column>
                    <flux:table.column class="w-32"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->groups as $group)
                        <flux:table.row wire:key="group-{{ $group->id }}" x-on:contextmenu.prevent.stop="contextMenu = 'group-{{ $group->id }}'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10">
                            <flux:table.cell>
                                <button type="button" wire:click.stop="edit({{ $group->id }})" x-on:click="contextMenu = null" class="hover:text-orange-700 cursor-pointer">{{ $group->title }}</button>
                            </flux:table.cell>
                            <flux:table.cell>{{ $group->handle }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($group->roles as $role)
                                        <flux:badge size="sm">{{ $role->title }}</flux:badge>
                                    @empty
                                        <span class="text-zinc-400">—</span>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>{{ $group->users_count }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-2 w-32">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item icon="pencil" wire:click="edit({{ $group->id }})">{{ __("Edit") }}</flux:menu.item>
                                        <flux:modal.trigger name="confirm-delete-group">
                                            <flux:menu.item icon="trash" variant="danger" x-on:click="deleteGroupId = {{ $group->id }}">{{ __("Delete") }}</flux:menu.item>
                                        </flux:modal.trigger>
                                    </flux:menu>
                                </flux:dropdown>

                                <div x-show="contextMenu === 'group-{{ $group->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    <button type="button" wire:click.stop="edit({{ $group->id }})" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Edit") }}</button>
                                    <flux:modal.trigger name="confirm-delete-group">
                                        <button type="button" x-on:click="deleteGroupId = {{ $group->id }}; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                                    </flux:modal.trigger>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __("No user groups yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="user-group-form" class="max-w-lg" :show="$errors->isNotEmpty()">
        <form wire:submit="save" class="space-y-6" x-on:keydown.window.ctrl.s.prevent="if ($el.closest('dialog')?.open) $wire.save();" x-on:keydown.window.meta.s.prevent="if ($el.closest('dialog')?.open) $wire.save();">
            <flux:heading size="lg">{{ $editingId ? __("Edit group") : __("Add group") }}</flux:heading>

            <flux:input size="sm" wire:model="title" :label="__('Title')" placeholder="Marketing" required autofocus />

            <div class="flex items-end gap-2">
                <div class="flex-1">
                    <flux:input size="sm" wire:model="handle" :label="__('Handle')" class="flex-1" required />
                </div>
                <flux:button size="sm" type="button" icon="arrow-path" wire:click="regenerateHandle" :tooltip="__('Regenerate from title')" />
            </div>

            <flux:field>
                <flux:label>{{ __("Roles") }}</flux:label>
                <flux:checkbox.group wire:model="roleHandles">
                    @forelse ($this->availableRoles as $role)
                        <flux:checkbox value="{{ $role->handle }}" label="{{ $role->title }}" />
                    @empty
                        <flux:text class="text-zinc-500">{{ __("No roles yet.") }}</flux:text>
                    @endforelse
                </flux:checkbox.group>
            </flux:field>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete-group" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this group?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteGroupId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
