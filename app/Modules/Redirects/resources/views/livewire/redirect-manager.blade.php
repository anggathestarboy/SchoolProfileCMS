<section class="w-full" x-data="{ deleteRedirectId: null }">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m16 10 3-3m0 0-3-3m3 3H5v3m3 4-3 3m0 0 3 3m-3-3h14v-3" />
            </svg>

            <flux:heading size="xl">{{ __("Redirect Manager") }}</flux:heading>
        </div>

        <flux:button variant="primary" size="sm" wire:click="create">{{ __("New redirect") }}</flux:button>
    </div>

    <div class="max-w-sm mt-3 mb-7">
        <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by source or target…') }}" icon="magnifying-glass" size="sm" />
    </div>

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white rounded-xl dark:bg-zinc-800">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column>{{ __("Source") }}</flux:table.column>
                    <flux:table.column>{{ __("Target") }}</flux:table.column>
                    <flux:table.column>{{ __("Status") }}</flux:table.column>
                    <flux:table.column>{{ __("Match type") }}</flux:table.column>
                    <flux:table.column>{{ __("Active") }}</flux:table.column>
                    <flux:table.column>{{ __("Hits") }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->redirects as $redirect)
                        <flux:table.row wire:key="redirect-{{ $redirect->id }}">
                            <flux:table.cell>{{ $redirect->source }}</flux:table.cell>
                            <flux:table.cell>{{ $redirect->target }}</flux:table.cell>
                            <flux:table.cell>{{ $redirect->status_code }}</flux:table.cell>
                            <flux:table.cell>{{ $redirect->match_type }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$redirect->is_active ? 'green' : 'zinc'">{{ $redirect->is_active ? __("Active") : __("Inactive") }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $redirect->hits }}</flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-1">
                                <flux:button size="sm" wire:click="view({{ $redirect->id }})">{{ __("View") }}</flux:button>
                                <flux:modal.trigger name="confirm-delete-redirect">
                                    <flux:button size="sm" variant="danger" type="button" x-on:click="deleteRedirectId = {{ $redirect->id }}">{{ __("Delete") }}</flux:button>
                                </flux:modal.trigger>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ $search !== "" ? __("No redirects match your search.") : __("No redirects yet.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="redirect-form" class="max-w-lg" :show="$errors->isNotEmpty()">
        <form wire:submit="save" class="space-y-6" x-on:keydown.window.ctrl.s.prevent="if ($el.closest('dialog')?.open) $wire.save()" x-on:keydown.window.meta.s.prevent="if ($el.closest('dialog')?.open) $wire.save()">
            <flux:heading size="lg">{{ $editingId ? __("Redirect details") : __("New redirect") }}</flux:heading>

            <div class="space-y-4">
                <flux:input wire:model="source" :label="__('Source')" placeholder="/old-path" required />
                <flux:input wire:model="target" :label="__('Target')" placeholder="/new-path" required />

                <div class="grid grid-cols-2 gap-4">
                    <flux:select wire:model="status_code" :label="__('Status code')">
                        <flux:select.option value="301">301 — {{ __("Permanent") }}</flux:select.option>
                        <flux:select.option value="302">302 — {{ __("Temporary") }}</flux:select.option>
                        <flux:select.option value="307">307 — {{ __("Temporary (keep method)") }}</flux:select.option>
                        <flux:select.option value="308">308 — {{ __("Permanent (keep method)") }}</flux:select.option>
                        <flux:select.option value="410">410 — {{ __("Gone") }}</flux:select.option>
                        <flux:select.option value="451">451 — {{ __("Unavailable for legal reasons") }}</flux:select.option>
                    </flux:select>

                    <flux:select wire:model.live="match_type" :label="__('Match type')">
                        <flux:select.option value="exact">{{ __("Exact") }}</flux:select.option>
                        <flux:select.option value="prefix">{{ __("Prefix") }}</flux:select.option>
                        <flux:select.option value="regex">{{ __("Regex") }}</flux:select.option>
                    </flux:select>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <flux:input type="datetime-local" wire:model="start_at" :label="__('Start at')" />
                    <flux:input type="datetime-local" wire:model="end_at" :label="__('End at')" />
                </div>

                <flux:switch wire:model="is_active" :label="__('Active')" />
                <flux:switch wire:model="is_external" :label="__('External target')" />

                @unless ($match_type === "exact")
                    <flux:switch wire:model="keep_path" :label="__('Keep path')" />
                @endunless

                <flux:text class="text-sm text-zinc-500">{{ __(":count hits so far.", ["count" => $hits]) }}</flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-delete-redirect" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this redirect?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteRedirectId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
