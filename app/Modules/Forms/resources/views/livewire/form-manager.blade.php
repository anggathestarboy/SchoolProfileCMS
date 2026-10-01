<section class="w-full space-y-6" x-data="{ deleteFormId: null, contextMenu: null, contextMenuX: 0, contextMenuY: 0 }" @click="contextMenu = null">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linejoin="round" stroke-width="2" d="M10 12v1h4v-1m4 7H6a1 1 0 0 1-1-1V9h14v9a1 1 0 0 1-1 1ZM4 5h16a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z" />
            </svg>

            <flux:heading size="xl">{{ __("Forms") }}</flux:heading>
        </div>

        <flux:button variant="primary" size="sm" wire:click="create">{{ __("New form") }}</flux:button>
    </div>

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white rounded-xl dark:bg-zinc-800">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column>{{ __("Title") }}</flux:table.column>
                    <flux:table.column>{{ __("Handle") }}</flux:table.column>
                    <flux:table.column>{{ __("Blueprint") }}</flux:table.column>
                    <flux:table.column>{{ __("Submissions") }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->forms as $form)
                        <flux:table.row wire:key="form-{{ $form->id }}" x-on:contextmenu.prevent.stop="contextMenu = 'form-{{ $form->id }}'; contextMenuX = $event.clientX - 20; contextMenuY = $event.clientY + 10">
                            <flux:table.cell>
                                <a href="{{route('forms.submissions', $form->handle)}}" class="hover:text-orange-700" wire:navigate> {{ $form->title }} </a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $form->handle }}</flux:table.cell>
                            <flux:table.cell>{{ $form->effectiveBlueprint()?->handle ?? "—" }}</flux:table.cell>
                            <flux:table.cell>{{ $form->submissions()->count() }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-2">
                                <flux:dropdown>
                                    <flux:button size="sm" variant="ghost" icon="ellipsis-vertical" />
                                    <flux:menu>
                                        <flux:menu.item icon="inbox" :href="route('forms.submissions', $form->handle)" wire:navigate>{{ __("Submissions") }}</flux:menu.item>
                                        <flux:menu.item icon="cog-6-tooth" wire:click="edit({{ $form->id }})">{{ __("Settings") }}</flux:menu.item>
                                        <flux:modal.trigger name="confirm-delete-form">
                                            <flux:menu.item icon="trash" variant="danger" x-on:click="deleteFormId = {{ $form->id }}">{{ __("Delete") }}</flux:menu.item>
                                        </flux:modal.trigger>
                                    </flux:menu>
                                </flux:dropdown>

                                <div x-show="contextMenu === 'form-{{ $form->id }}'" x-cloak x-on:click.outside="contextMenu = null" :style="{ left: contextMenuX + 'px', top: contextMenuY + 'px' }" class="fixed z-50 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                                    <a href="{{ route('forms.submissions', $form->handle) }}" wire:navigate x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Submissions") }}</a>
                                    <button type="button" wire:click.stop="edit({{ $form->id }})" x-on:click="contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Settings") }}</button>
                                    <flux:modal.trigger name="confirm-delete-form">
                                        <button type="button" x-on:click="deleteFormId = {{ $form->id }}; contextMenu = null" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Delete") }}</button>
                                    </flux:modal.trigger>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5">{{ __("No forms yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
        <div>
            <flux:modal name="form-form" class="max-w-lg" :show="$errors->isNotEmpty()">
                <form wire:submit="save" class="space-y-6">
                    <flux:heading size="lg">{{ $editingId ? __("Edit form") : __("New form") }}</flux:heading>

                    <flux:input wire:model="title" :label="__('Title')" placeholder="Contact" size="sm" required autofocus />

                    <div class="flex items-end gap-2">
                        <div class="flex-1">
                            <flux:input wire:model="handle" :label="__('Handle')" size="sm" placeholder="contact" required />
                        </div>
                        <flux:button size="sm" type="button" icon="arrow-path" wire:click="regenerateHandle" :tooltip="__('Regenerate from title')" />
                    </div>

                    @unless ($createBlueprint)
                        <flux:select wire:model.live="blueprint" size="sm" :label="__('Blueprint')" placeholder="{{ __('No blueprint') }}">
                            @foreach ($this->availableBlueprints as $availableBlueprint)
                                <flux:select.option value="{{ $availableBlueprint->handle }}"> {{ $availableBlueprint->namespace ? "{$availableBlueprint->namespace}.{$availableBlueprint->handle}" : $availableBlueprint->handle }} </flux:select.option>
                            @endforeach
                        </flux:select>

                        @if ($this->availableBlueprints->isEmpty())
                            <flux:text class="text-zinc-500">{{ __("No blueprints yet — create one in the Blueprints module first.") }}</flux:text>
                        @endif

                        @if ($this->selectedBlueprintFields !== [])
                            <flux:checkbox.group wire:model="previewFields" :label="__('Preview columns')" :description="__('Which fields show as columns in the submissions table. Leave all unchecked to show every field.')">
                                @foreach ($this->selectedBlueprintFields as $field)
                                    <flux:checkbox value="{{ $field['handle'] }}" label="{{ $field['display'] ?: $field['handle'] }}" />
                                @endforeach
                            </flux:checkbox.group>
                        @endif
                    @endunless

                    @unless ($editingId)
                        <flux:checkbox wire:model.live="createBlueprint" :label="__('Create a blueprint for this form')" :description="__('Recommended — you\'ll add its fields right after saving. Turn off to pick an existing blueprint instead.')" />
                    @endunless

                    <flux:switch wire:model="storeSubmissions" :label="__('Store submissions')" :description="__('Save every successful submission to the Submissions table below.')" />

                    <flux:input wire:model="redirectUrl" :label="__('Redirect URL')" placeholder="https://example.com/thank-you" size="sm" :description="__('Where a successful submission redirects to. Leave blank to redirect back to the page the form was on.')" />

                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                        </flux:modal.close>
                        <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="confirm-delete-form" class="min-w-[24rem]">
                <div class="space-y-4">
                    <flux:heading size="lg">{{ __("Delete this form?") }}</flux:heading>
                    <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
                    <div class="flex justify-end gap-2">
                        <flux:modal.close>
                            <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                        </flux:modal.close>
                        <flux:modal.close>
                            <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteFormId)">{{ __("Delete") }}</flux:button>
                        </flux:modal.close>
                    </div>
                </div>
            </flux:modal>
</section>
