<section class="w-full" x-data="{ deleteUserId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Zm0 0a8.949 8.949 0 0 0 4.951-1.488A3.987 3.987 0 0 0 13 16h-2a3.987 3.987 0 0 0-3.951 3.512A8.948 8.948 0 0 0 12 21Zm3-11a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>

            <flux:heading size="xl">{{ __("Users") }}</flux:heading>
        </div>

        @if (auth()->user()->hasPermission("create users"))
            <flux:button variant="primary" size="sm" :href="route('users.create')" wire:navigate>{{ __("Add user") }}</flux:button>
        @endif
    </div>

    <div class="flex items-center justify-between gap-4 mt-3 mb-7">
        <div class="max-w-sm flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by name or email…') }}" icon="magnifying-glass" size="sm" />
        </div>

        @if (count($selected) > 0)
            <div class="flex items-end gap-2">
                <flux:text class="text-sm text-zinc-500">{{ __(":count selected", ["count" => count($selected)]) }}</flux:text>
                @if (auth()->user()->hasPermission("ban users"))
                    <flux:button size="sm" wire:click="bulkUnban">{{ __("Unban") }}</flux:button>
                    <flux:button size="sm" wire:click="bulkBan">{{ __("Ban") }}</flux:button>
                @endif
                @if (auth()->user()->hasPermission("delete users"))
                    <flux:modal.trigger name="confirm-bulk-delete-users">
                        <flux:button size="sm" variant="danger" type="button">{{ __("Delete") }}</flux:button>
                    </flux:modal.trigger>
                @endif
            </div>
        @endif
    </div>

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white dark:bg-zinc-800 rounded-xl overflow-hidden">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column class="w-10" :border="false">
                        <input type="checkbox" wire:click="toggleSelectAll" :checked="count($selected) > 0 && count($selected) === $this->users->count()" class="rounded w-max border-zinc-300 dark:border-zinc-600" />
                    </flux:table.column>
                    <flux:table.column :border="false" class="w-[15%]">{{ __("Name") }}</flux:table.column>
                    <flux:table.column :border="false">{{ __("Email") }}</flux:table.column>
                    <flux:table.column :border="false">{{ __("Status") }}</flux:table.column>
                    <flux:table.column :border="false">{{ __("Roles") }}</flux:table.column>
                    <flux:table.column :border="false">{{ __("Groups") }}</flux:table.column>
                    <flux:table.column :border="false" class="w-32"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->users as $user)
                        @php
                            $isSelf = $user->id === auth()->id();
                            $canEditUser = auth()->user()->hasPermission("edit users");
                            $canSendPasswordReset = auth()->user()->hasPermission("send password reset");
                            $canOperate = $canEditUser || $canSendPasswordReset || (!$isSelf && (auth()->user()->hasPermission("ban users") || auth()->user()->hasPermission("delete users")));
                        @endphp
                        <flux:table.row wire:key="user-{{ $user->id }}" x-on:contextmenu.prevent.stop="{{ $canOperate ? 'contextMenu = \'user-' . $user->id . '\'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10' : '' }}">
                            <flux:table.cell>
                                @if ($user->id === auth()->id())
                                    <input type="checkbox" disabled title="{{ __("You can't select your own account.") }}" class="rounded border-zinc-300 dark:border-zinc-600" />
                                @else
                                    <input type="checkbox" wire:model.live="selected" value="{{ $user->id }}" class="rounded border-zinc-300 dark:border-zinc-600" />
                                @endif
                            </flux:table.cell>
                            <flux:table.cell class="w-[15%]">
                                <a href="{{route('users.edit', ['user' => $user->id])}}" class="hover:text-orange-700" wire:navigate> {{ $user->name }} </a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $user->email }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($user->isBanned())
                                    <flux:badge size="sm" color="red">{{ __("Banned") }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="green">{{ __("Active") }}</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->roles as $role)
                                        <flux:badge size="sm">{{ $role->title }}</flux:badge>
                                    @empty
                                        <span class="text-zinc-400">—</span>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex flex-wrap gap-1">
                                    @forelse ($user->userGroups as $group)
                                        <flux:badge size="sm" color="zinc">{{ $group->title }}</flux:badge>
                                    @empty
                                        <span class="text-zinc-400">—</span>
                                    @endforelse
                                </div>
                            </flux:table.cell>
                            <flux:table.cell class="flex justify-end w-full">
                                @if ($canOperate)
                                    <flux:dropdown>
                                        <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                        <flux:menu>
                                            @if ($canEditUser)
                                                <flux:menu.item icon="pencil" :href="route('users.edit', ['user' => $user->id])" wire:navigate>{{ __("Edit") }}</flux:menu.item>
                                            @endif
                                            @if ($canSendPasswordReset)
                                                <flux:menu.item icon="key" wire:click="sendPasswordReset('{{ $user->id }}')">{{ __("Send password reset") }}</flux:menu.item>
                                            @endif
                                            @if (!$isSelf)
                                                @if (auth()->user()->hasPermission("ban users"))
                                                    @if ($user->isBanned())
                                                        <flux:menu.item icon="arrow-path" wire:click="unban('{{ $user->id }}')">{{ __("Unban") }}</flux:menu.item>
                                                    @else
                                                        <flux:menu.item icon="hand-raised" wire:click="ban('{{ $user->id }}')">{{ __("Ban") }}</flux:menu.item>
                                                    @endif
                                                @endif
                                                @if (auth()->user()->hasPermission("delete users"))
                                                    <flux:modal.trigger name="confirm-delete-user">
                                                        <flux:menu.item icon="trash" variant="danger" x-on:click="deleteUserId = '{{ $user->id }}'">{{ __("Delete") }}</flux:menu.item>
                                                    </flux:modal.trigger>
                                                @endif
                                            @endif
                                        </flux:menu>
                                    </flux:dropdown>
                                @endif

                                @if ($canOperate)
                                    <div x-show="contextMenu === 'user-{{ $user->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                        @if ($canEditUser)
                                            <a href="{{ route('users.edit', ['user' => $user->id]) }}" wire:navigate x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Edit") }}</a>
                                        @endif
                                        @if ($canSendPasswordReset)
                                            <button type="button" wire:click.stop="sendPasswordReset('{{ $user->id }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Send password reset") }}</button>
                                        @endif
                                        @if (!$isSelf)
                                            @if (auth()->user()->hasPermission("ban users"))
                                                @if ($user->isBanned())
                                                    <button type="button" wire:click.stop="unban('{{ $user->id }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Unban") }}</button>
                                                @else
                                                    <button type="button" wire:click.stop="ban('{{ $user->id }}')" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Ban") }}</button>
                                                @endif
                                            @endif
                                            @if (auth()->user()->hasPermission("delete users"))
                                                <flux:modal.trigger name="confirm-delete-user">
                                                    <button type="button" x-on:click="deleteUserId = '{{ $user->id }}'; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                                                </flux:modal.trigger>
                                            @endif
                                        @endif
                                    </div>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ $search !== "" ? __("No users match your search.") : __("No users yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="confirm-delete-user" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this user?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteUserId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-bulk-delete-users" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete :count user(s)?", ["count" => count($selected)]) }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone. Your own account, if selected, is skipped.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" wire:click="bulkDelete">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
