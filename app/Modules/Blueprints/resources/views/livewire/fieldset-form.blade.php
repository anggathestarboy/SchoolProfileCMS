<section class="w-full max-w-6xl space-y-6" x-data="{ deleteFieldPath: null }" x-on:keydown.window.ctrl.s.prevent="$wire.save()" x-on:keydown.window.meta.s.prevent="$wire.save()">
    <div>
        <flux:link :href="route('blueprints.fieldsets')" variant="subtle" wire:navigate class="text-sm">{{ __("← Fieldsets") }}</flux:link>
        <flux:heading size="lg" class="mt-5">{{ $editingId ? __("Edit fieldset") : __("New fieldset") }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <flux:input wire:model="handle" :label="__('Handle')" description:trailing="{{ __('Lowercase letters, numbers and underscores only — no spaces.') }}" required />

        <div class="space-y-3">
            <div class="flex items-center justify-between bg-white z-10 sticky top-0 p-2 border rounded-b-xl dark:bg-zinc-900 dark:border-zinc-700">
                <flux:switch wire:model.live="fieldsCollapsedByDefault" size="sm" :label="__('Collapse fields by default')" />
                <div class="flex gap-2">
                    <flux:button size="xs" variant="ghost" type="button" wire:click="$dispatch('collapse-all-fields')">{{ __("Collapse all") }}</flux:button>
                    <flux:button size="xs" variant="ghost" type="button" wire:click="$dispatch('expand-all-fields')">{{ __("Expand all") }}</flux:button>
                    <flux:button size="xs" type="button" wire:click="addField">{{ __("Add field") }}</flux:button>
                </div>
            </div>

            @error ("fields")
                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            <div class="space-y-3" wire:sort="reorderFields" wire:sort:group="fieldset-fields" wire:sort:group-id="{{ json_encode(['tab' => 'main', 'section' => '']) }}">
                @foreach ($fields as $index => $field)
                    @include ("blueprints::livewire.partials.field-editor",
                        [
                            "field" => $field,
                            "path" => [$index],
                            "prefix" => "fields.{$index}",
                            "index" => $index,
                            "isFirst" => $index === 0,
                            "isLast" => $index === count($fields) - 1,
                            "siblings" => $fields,
                            "fieldtypes" => $fieldtypes,
                            "conditionOperators" => $conditionOperators,
                            "assetContainers" => $assetContainers,
                            "linkableCollections" => $linkableCollections,
                            "fieldsets" => $fieldsets,
                            "taxonomies" => $taxonomies,
                            "bardToolbarButtons" => $bardToolbarButtons,
                            "tabs" => $tabs
                        ])
                @endforeach
            </div>

            @if (empty($fields))
                <flux:text class="text-sm text-zinc-500">{{ __("No fields yet.") }}</flux:text>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('blueprints.fieldsets')" wire:navigate variant="filled">{{ __("Cancel") }}</flux:button>
            <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
        </div>
    </form>

    <flux:modal name="confirm-remove-field" class="min-w-[22rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this field?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" wire:click="removeField(deleteFieldPath)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
