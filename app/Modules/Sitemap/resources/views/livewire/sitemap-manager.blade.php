<section class="w-full" x-data="{ deleteUrlId: null }">
    <div class="flex items-center justify-between border py-2 px-4 rounded-xl dark:border-zinc-500">
        <div class="flex gap-2 items-center">
            <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.041 13.862A4.999 4.999 0 0 1 17 17.831V21M7 3v3.169a5 5 0 0 0 1.891 3.916M17 3v3.169a5 5 0 0 1-2.428 4.288l-5.144 3.086A5 5 0 0 0 7 17.831V21M7 5h10M7.399 8h9.252M8 16h8.652M7 19h10" />
            </svg>

            <flux:heading size="xl">{{ __("Sitemap Manager") }}</flux:heading>
        </div>

        <flux:button size="sm" variant="primary" wire:click="regenerate">{{ __("Regenerate") }}</flux:button>
    </div>

    <div class="flex items-center justify-between gap-4 mt-3 mb-7">
        <div class="max-w-sm flex-1">
            <flux:input wire:model.live.debounce.300ms="search" placeholder="{{ __('Search by URL…') }}" icon="magnifying-glass" size="sm" />
        </div>

        <flux:button size="sm" :href="route('sitemap')" target="_blank" icon="arrow-top-right-on-square">{{ __("View sitemap.xml") }}</flux:button>
    </div>

    <div class="bg-gray-200 dark:bg-zinc-900 p-1.5 rounded-xl">
        <div class="bg-white rounded-xl dark:bg-zinc-800">
            <flux:table>
                <flux:table.columns class="bg-gray-200 dark:bg-zinc-900 [&>*]:text-white">
                    <flux:table.column>{{ __("URL") }}</flux:table.column>
                    <flux:table.column>{{ __("Collection") }}</flux:table.column>
                    <flux:table.column>{{ __("Priority") }}</flux:table.column>
                    <flux:table.column>{{ __("Changefreq") }}</flux:table.column>
                    <flux:table.column>{{ __("Last modified") }}</flux:table.column>
                    <flux:table.column>{{ __("Active") }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($this->urls as $url)
                        <flux:table.row wire:key="sitemap-{{ $url->id }}">
                            <flux:table.cell class="max-w-md truncate">
                                <a href="{{ $url->url }}" target="_blank" class="hover:underline">{{ $url->url }}</a>
                            </flux:table.cell>
                            <flux:table.cell>{{ $url->collection }}</flux:table.cell>
                            <flux:table.cell>{{ number_format($url->priority, 1) }}</flux:table.cell>
                            <flux:table.cell>{{ $url->changefreq }}</flux:table.cell>
                            <flux:table.cell>{{ $url->lastmod?->diffForHumans() ?? "—" }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:switch wire:click="toggleActive({{ $url->id }})" :checked="$url->is_active" />
                            </flux:table.cell>
                            <flux:table.cell class="flex justify-end gap-1">
                                <flux:modal.trigger name="confirm-delete-url">
                                    <flux:button size="sm" variant="danger" type="button" x-on:click="deleteUrlId = {{ $url->id }}">{{ __("Delete") }}</flux:button>
                                </flux:modal.trigger>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="7">{{ $search !== "" ? __("No URLs match your search.") : __("No sitemap entries yet — publish an entry to see it here.") }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </div>

    <flux:modal name="confirm-delete-url" class="min-w-[24rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this sitemap entry?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("It comes back automatically the next time its entry is saved, as long as the entry is still published.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" x-on:click="$wire.delete(deleteUrlId)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
