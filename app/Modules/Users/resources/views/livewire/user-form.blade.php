<section class="w-full max-w-3xl space-y-6" x-on:keydown.window.ctrl.s.prevent="$wire.save()" x-on:keydown.window.meta.s.prevent="$wire.save()">
    <div class="flex items-center justify-start gap-2 border py-2 px-4 rounded-xl dark:border-zinc-500">
        <flux:link :href="route('users.index')" variant="subtle" wire:navigate class="text-sm">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="square" stroke-linejoin="round" stroke-width="2" d="M10 19H5a1 1 0 0 1-1-1v-1a3 3 0 0 1 3-3h2m10 1a3 3 0 0 1-3 3m3-3a3 3 0 0 0-3-3m3 3h1m-4 3a3 3 0 0 1-3-3m3 3v1m-3-4a3 3 0 0 1 3-3m-3 3h-1m4-3v-1m-2.121 1.879-.707-.707m5.656 5.656-.707-.707m-4.242 0-.707.707m5.656-5.656-.707.707M12 8a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
            </svg>
        </flux:link>
        <flux:heading size="xl">{{ $editingId ? __("Edit user") : __("Add user") }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4">
            <flux:input wire:model="name" :label="__('Name')" placeholder="Jane Doe" required autofocus />
            <flux:input wire:model="email" type="email" :label="__('Email')" placeholder="jane@example.com" required />
        </div>

        @if (! $editingId)
            <flux:field>
                <flux:label>{{ __("Password") }}</flux:label>
                <flux:radio.group wire:model.live="passwordMode">
                    <flux:radio value="admin" label="{{ __('Set password now') }}" />
                    <flux:radio value="reset_link" label="{{ __('Email the user a link to set their own password') }}" />
                </flux:radio.group>
            </flux:field>

            @if ($passwordMode === "admin")
                <flux:input wire:model="password" type="password" :label="__('Password')" placeholder="••••••••" required />
            @else
                <flux:text class="text-sm text-zinc-500">{{ __('A password-reset link will be emailed to them once saved — they set their own password from there.') }}</flux:text>
            @endif
        @elseif (auth()->user()->hasPermission("change passwords"))
            <flux:input wire:model="password" type="password" :label="__('Password')" :description="__('Leave blank to keep the current password.')" placeholder="••••••••" />
        @endif

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

        <flux:field>
            <flux:label>{{ __("User groups") }}</flux:label>
            <flux:checkbox.group wire:model="groupHandles">
                @forelse ($this->availableGroups as $group)
                    <flux:checkbox value="{{ $group->handle }}" label="{{ $group->title }}" />
                @empty
                    <flux:text class="text-zinc-500">{{ __("No user groups yet.") }}</flux:text>
                @endforelse
            </flux:checkbox.group>
        </flux:field>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('users.index')" variant="filled" wire:navigate>{{ __("Cancel") }}</flux:button>
            <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
        </div>
    </form>
</section>
