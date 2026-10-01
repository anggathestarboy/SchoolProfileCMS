<section class="w-full max-w-4xl space-y-6">
    <div>
        <flux:heading size="lg">{{ __('404 Detector') }}</flux:heading>
        <flux:subheading>{{ __('Scan published entries for broken public links.') }}</flux:subheading>
    </div>

    <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
        <flux:field>
            <div class="mb-1 flex items-center justify-between">
                <flux:label>{{ __('Collections') }}</flux:label>
                <flux:button size="xs" type="button" wire:click="toggleAllCollections">{{ __('Select / Deselect all') }}</flux:button>
            </div>
            <flux:checkbox.group wire:model="selectedCollections">
                @forelse ($this->availableCollections as $collection)
                    <flux:checkbox value="{{ $collection->handle }}" label="{{ $collection->title }}" />
                @empty
                    <flux:text class="text-zinc-500">{{ __('No collections yet.') }}</flux:text>
                @endforelse
            </flux:checkbox.group>
            <flux:description>{{ __('Leave everything unchecked to scan every collection.') }}</flux:description>
        </flux:field>

        <flux:button variant="primary" wire:click="scan" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="scan">{{ __('Scan') }}</span>
            <span wire:loading wire:target="scan">{{ __('Scanning…') }}</span>
        </flux:button>
    </div>

    @if ($hasScanned)
        @if (empty($results))
            <flux:callout variant="success" icon="check-circle" :heading="__('No broken links found.')" />
        @else
            <flux:callout variant="danger" icon="exclamation-triangle" :heading="__(':count entries with a broken public link.', ['count' => count($results)])" />

            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Entry') }}</flux:table.column>
                    <flux:table.column>{{ __('Collection') }}</flux:table.column>
                    <flux:table.column>{{ __('URL') }}</flux:table.column>
                    <flux:table.column>{{ __('Reason') }}</flux:table.column>
                    <flux:table.column class="w-24"></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($results as $result)
                        <flux:table.row wire:key="result-{{ $result['entry']->id }}">
                            <flux:table.cell>{{ $result['entry']->data['title'] ?? $result['entry']->slug ?? $result['entry']->id }}</flux:table.cell>
                            <flux:table.cell>{{ $result['collection']?->title ?? $result['entry']->collection }}</flux:table.cell>
                            <flux:table.cell class="text-xs text-zinc-500">{{ $result['url'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell class="text-xs">{{ $result['reason'] }}</flux:table.cell>
                            <flux:table.cell class="w-24">
                                @if ($result['collection'])
                                    <flux:button size="sm" :href="route('collections.entries.edit', ['collection' => $result['collection']->handle, 'entry' => $result['entry']->id])" wire:navigate>{{ __('Edit') }}</flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    @endif
</section>
