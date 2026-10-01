@php
    $groupId = json_encode(['path' => $path]);
    $groupName = 'menu-items-' . implode('-', $path);
@endphp

<div>
    <flux:text class="text-sm font-medium" title="{{ $field['handle'] }}">{{ $field['display'] }}</flux:text>
    @if ($field['instructions'] ?? null)
        <flux:text class="block text-xs text-zinc-500">{{ $field['instructions'] }}</flux:text>
    @endif

    <div class="mt-2 space-y-2" wire:sort="reorderReplicatorRow" wire:sort:group="{{ $groupName }}" wire:sort:group-id="{{ $groupId }}">
        @forelse ($rows as $rowIndex => $row)
            @include('navigation::livewire.partials.menu-item', [
                'field' => $field,
                'path' => $path,
                'row' => $row,
                'rowIndex' => $rowIndex,
            ])
        @empty
            <flux:text class="text-sm text-zinc-500">{{ __('No items yet.') }}</flux:text>
        @endforelse
    </div>

    <flux:button size="sm" type="button" class="mt-2" icon:trailing="plus" wire:click='addReplicatorRow({{ json_encode($path) }})'>{{ __('Add :label', ['label' => $field['display']]) }}</flux:button>
</div>
