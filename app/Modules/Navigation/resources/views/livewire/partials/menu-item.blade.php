@php
    $childReplicatorField = collect($field["replicator_fields"] ?? [])->first(fn($sub) => $sub["type"] === "replicator");
    $previewField = collect($field["replicator_fields"] ?? [])->first(fn($sub) => $sub["type"] === "text");
    $preview = trim((string) ($row[$previewField["handle"] ?? null] ?? ""));
    $preview = $preview !== "" ? $preview : __("Untitled");
    $rowUid = $row["_uid"] ?? $rowIndex;
    $replicatorConditionTargets = $this->conditionTargetHandles($field["replicator_fields"] ?? []);
@endphp

<div wire:key="menu-item-{{ $rowUid }}-{{ $rowIndex }}" wire:sort:item="{{ $rowUid }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700" x-data="{ expanded: true }">
    <div class="flex items-center gap-2 rounded-t-lg bg-zinc-50 p-2 cursor-pointer dark:bg-zinc-800" x-on:click="expanded = !expanded">
        <span wire:sort:handle x-on:click.stop class="cursor-grab text-zinc-400 hover:text-zinc-600 active:cursor-grabbing dark:hover:text-zinc-300" title="{{ __('Drag to reorder') }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                <circle cx="5" cy="3" r="1.3" />
                <circle cx="11" cy="3" r="1.3" />
                <circle cx="5" cy="8" r="1.3" />
                <circle cx="11" cy="8" r="1.3" />
                <circle cx="5" cy="13" r="1.3" />
                <circle cx="11" cy="13" r="1.3" />
            </svg>
        </span>

        <span class="inline-flex items-center rounded-full border border-zinc-200 bg-white px-2 py-0.5 text-xs font-medium text-zinc-700 dark:border-zinc-600 dark:bg-zinc-700 dark:text-white" title="{{ $field['handle'] }}">{{ $field["display"] }}</span>

        <flux:text class="min-w-0 flex-1 truncate text-sm">{{ $preview }}</flux:text>

        <div class="relative" x-data="{ open: false }" x-on:click.stop x-on:click.outside="open = false">
            <button type="button" x-on:click="open = !open" class="rounded p-1.5 hover:bg-zinc-200 dark:hover:bg-zinc-700">
                <flux:icon.ellipsis-horizontal class="size-4" />
            </button>

            <div x-show="open" x-cloak class="absolute right-0 z-10 mt-1 w-40 rounded-md border border-zinc-200 bg-white py-1 shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                <button type="button" x-on:click="open = false; $wire.duplicateReplicatorRow({{ json_encode($path) }}, {{ $rowIndex }})" class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800">{{ __("Duplicate") }}</button>
                <button
                    type="button"
                    x-on:click="
                        open = false;
                        expanded = !expanded;
                    "
                    class="block w-full px-3 py-1.5 text-left text-sm hover:bg-zinc-100 dark:hover:bg-zinc-800"
                    x-text="expanded ? '{{ __('Collapse') }}' : '{{ __('Expand') }}'"
                ></button>
                <button type="button" x-on:click="open = false; $wire.removeReplicatorRow({{ json_encode($path) }}, {{ $rowIndex }})" class="block w-full px-3 py-1.5 text-left text-sm text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">{{ __("Delete") }}</button>
            </div>
        </div>
    </div>

    <div x-show="expanded" x-cloak class="space-y-4 border-t border-zinc-200 p-3 dark:border-zinc-700">
        <div class="grid gap-3">
            @foreach ($field["replicator_fields"] ?? [] as $sub)
                @continue ($sub["type"] === "replicator")
                @if ($this->fieldIsVisible($sub, $row))
                    <div class="{{ in_array($sub['type'], ['link', 'textarea', 'markdown', 'bard'], true) ? 'col-span-2' : '' }}">
                        @include ("collections::livewire.partials.entry-field",
                            [
                                "field" => $sub,
                                "path" => [...$path, $rowIndex, $sub["handle"]],
                                "assetIds" => $sub["type"] === "assets" ? $row[$sub["handle"]] ?? [] : null,
                                "linkValue" => $sub["type"] === "link" ? $row[$sub["handle"]] ?? "" : null,
                                "taxonomyIds" => $sub["type"] === "taxonomy" ? $row[$sub["handle"]] ?? [] : null,
                                "bardValue" => $sub["type"] === "bard" ? $row[$sub["handle"]] ?? "" : null,
                                "forceLive" => in_array($sub["handle"], $replicatorConditionTargets, true)
                            ])
                    </div>
                @endif
            @endforeach
        </div>

        @if ($childReplicatorField)
            <div class="border-l-2 border-zinc-200 pl-4 dark:border-zinc-700">
                @include ("navigation::livewire.partials.menu-list",
                    [
                        "field" => $childReplicatorField,
                        "path" => [...$path, $rowIndex, $childReplicatorField["handle"]],
                        "rows" => $row[$childReplicatorField["handle"]] ?? []
                    ])
            </div>
        @endif
    </div>
</div>
