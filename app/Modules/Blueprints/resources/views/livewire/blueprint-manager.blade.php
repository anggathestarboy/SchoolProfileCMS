<section class="w-full space-y-6" x-data="{ deleteBlueprintId: null }">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13v-2a1 1 0 0 0-1-1h-.757l-.707-1.707.535-.536a1 1 0 0 0 0-1.414l-1.414-1.414a1 1 0 0 0-1.414 0l-.536.535L14 4.757V4a1 1 0 0 0-1-1h-2a1 1 0 0 0-1 1v.757l-1.707.707-.536-.535a1 1 0 0 0-1.414 0L4.929 6.343a1 1 0 0 0 0 1.414l.536.536L4.757 10H4a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h.757l.707 1.707-.535.536a1 1 0 0 0 0 1.414l1.414 1.414a1 1 0 0 0 1.414 0l.536-.535 1.707.707V20a1 1 0 0 0 1 1h2a1 1 0 0 0 1-1v-.757l1.707-.708.536.536a1 1 0 0 0 1.414 0l1.414-1.414a1 1 0 0 0 0-1.414l-.535-.536.707-1.707H20a1 1 0 0 0 1-1Z" />
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
            </svg>

            <flux:heading size="xl">{{ __("Blueprints") }}</flux:heading>
        </div>

        <div class="flex gap-2">
            <flux:button size="sm" :href="route('blueprints.fieldsets')" wire:navigate>{{ __("Fieldsets") }}</flux:button>
            <flux:button size="sm" variant="primary" :href="route('blueprints.create')" wire:navigate>{{ __("New blueprint") }}</flux:button>
        </div>
    </div>

    @if ($this->blueprints->isEmpty())
        <flux:text class="text-zinc-500">{{ __("No blueprints yet.") }}</flux:text>
    @else
        @php
            $categories = [
                "collections" => __("Collections"),
                "globals" => __("Globals"),
                "taxonomies" => __("Taxonomies"),
                "navigations" => __("Navigation"),
                "forms" => __("Forms"),
                "other" => __("Other"),
            ];
        @endphp

        @foreach ($categories as $categoryKey => $categoryLabel)
            @php $group = $this->blueprintGroups->get($categoryKey, collect()); @endphp
            @if ($group->isNotEmpty())
                <div class="bg-gray-200/80 px-2 py-2 rounded-2xl dark:bg-zinc-950">
                    <flux:heading size="md" class="pt-1 px-2 pb-2">{{ $categoryLabel }}</flux:heading>
                    <div class="space-y-3 bg-white dark:bg-zinc-800 px-3 py-1 rounded-xl">
                        <flux:table>
                            <flux:table.columns>
                                <flux:table.column :border="true" class="">{{ __("Namespace") }}</flux:table.column>
                                <flux:table.column :border="true" class="w-[400px]">{{ __("Handle") }}</flux:table.column>
                                <flux:table.column :border="true" class="w-[100px]">{{ __("Fields") }}</flux:table.column>
                                <flux:table.column :border="true" class="w-[150px]"></flux:table.column>
                            </flux:table.columns>

                            <flux:table.rows>
                                @foreach ($group as $blueprint)
                                    <flux:table.row wire:key="blueprint-{{ $blueprint->id }}">
                                        <flux:table.cell>{{ $blueprint->namespace ?? "—" }}</flux:table.cell>
                                        <flux:table.cell>{{ $blueprint->handle }}</flux:table.cell>
                                        <flux:table.cell>{{ count($blueprint->data["fields"] ?? []) }}</flux:table.cell>
                                        <flux:table.cell class="flex justify-end gap-2">
                                            <flux:button size="xs" :href="route('blueprints.edit', $blueprint)" wire:navigate>{{ __("Edit") }}</flux:button>
                                            <flux:modal.trigger name="confirm-delete-blueprint">
                                                <flux:button size="xs" variant="danger" type="button" x-on:click="deleteBlueprintId = {{ $blueprint->id }}">{{ __("Delete") }}</flux:button>
                                            </flux:modal.trigger>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                </div>
            @endif
        @endforeach
    @endif

    <flux:modal name="confirm-delete-blueprint" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this blueprint?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteBlueprintId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
