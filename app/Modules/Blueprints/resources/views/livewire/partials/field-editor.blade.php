@php
    $field = array_merge(
        [
            "_uid" => implode("-", $path),
            "handle" => "",
            "display" => "",
            "type" => "text",
            "required" => false,
            "localizable" => false,
            "options" => "",
            "placeholder" => "",
            "default" => "",
            "validation" => "",
            "instructions" => "",
            "sometimes" => false,
            "condition_mode" => "always",
            "condition_field" => "",
            "condition_operator" => "equals",
            "condition_value" => "",
            "tab" => "main",
            "section" => "",
            "replicator_fields" => [],
        ],
        $field,
    );
    $pathJson = json_encode($path);
    $isTopLevelField = count($path) === 1;
    $fieldTabSections = collect($tabs ?? [])->firstWhere("handle", $field["tab"])["sections"] ?? [];
@endphp

<div wire:key="field-{{ implode('-', $path) }}" wire:sort:item="{{ $field['_uid'] }}" class="rounded-lg border border-gray-200 dark:bg-zinc-800 px-1.5 py-1 dark:border-zinc-600 bg-gray-100" x-data="{ collapsed: @js($fieldsCollapsedByDefault ?? false) }" x-on:collapse-all-fields.window="collapsed = true" x-on:expand-all-fields.window="collapsed = false">
    <div class="flex items-center justify-between bg-gray-100 dark:bg-zinc-800 rounded-lg w-full">
        <span wire:sort:handle class="flex h-full cursor-grab items-center px-1 text-zinc-400 hover:text-zinc-600 active:cursor-grabbing dark:hover:text-zinc-300" title="{{ __('Drag to reorder') }}">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="size-4">
                <circle cx="5" cy="3" r="1.3" />
                <circle cx="11" cy="3" r="1.3" />
                <circle cx="5" cy="8" r="1.3" />
                <circle cx="11" cy="8" r="1.3" />
                <circle cx="5" cy="13" r="1.3" />
                <circle cx="11" cy="13" r="1.3" />
            </svg>
        </span>

        <flux:text class="flex-1 self-center flex items-center gap-1.5 text-sm font-medium hover:bg-black/5 rounded-md cursor-pointer py-1.5 px-3" x-on:click="collapsed = !collapsed">
            {{ $field["type"] === "fieldset" ? ($field["options"] ?: __("Untitled field")) : ($field["display"] ?: $field["handle"] ?: __("Untitled field")) }}
            <span class="text-zinc-400 text-xs">({{ $fieldtypes[$field["type"]] ?? $field["type"] }})</span>
        </flux:text>

        <div class="flex gap-1.5">
            <div class="flex gap-2 items-center">
                @if ($field["localizable"] ?? false)
                    <flux:icon.language class="size-3.5 text-blue-700" title="{{ __('Localizable') }}" />
                @endif
                @if ($field["required"] ?? false)
                    <flux:icon.exclamation-circle class="size-3.5 text-red-500" title="{{ __('Required') }}" />
                @endif
            </div>
            <div class="">
                <flux:button size="xs" variant="ghost" icon="minus" type="button" class="cursor-pointer" x-show="!collapsed" x-on:click="collapsed = !collapsed" />
                <flux:button size="xs" variant="ghost" icon="plus" type="button" class="cursor-pointer" x-show="collapsed" x-cloak x-on:click="collapsed = !collapsed" />
            </div>
            <flux:button size="xs" variant="ghost" icon="document-duplicate" type="button" class="cursor-pointer" title="{{ __('Duplicate') }}" wire:click="duplicateField({{ $pathJson }})" />
            <flux:modal.trigger name="confirm-remove-field">
                <flux:button size="xs" variant="ghost" icon="trash" type="button" class="cursor-pointer" x-on:click="deleteFieldPath = {{ $pathJson }}" />
            </flux:modal.trigger>

            <div class="">
                {{-- @if ($isTopLevelField && (count($tabs) > 1 || $fieldTabSections !== []))
                    <div class="flex gap-2">
                        @if (count($tabs) > 1)
                            <flux:select wire:model.live="{{ $prefix }}.tab" size="xs">
                                @foreach ($tabs as $tabOption)
                                    <flux:select.option value="{{ $tabOption['handle'] }}">{{ $tabOption["display"] ?: $tabOption["handle"] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif

                        @if ($fieldTabSections !== [])
                            <flux:select wire:model="{{ $prefix }}.section" placeholder="{{ __('No section') }}" size="xs">
                                @foreach ($fieldTabSections as $sectionOption)
                                    <flux:select.option value="{{ $sectionOption['handle'] }}">{{ $sectionOption["display"] ?: $sectionOption["handle"] }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        @endif
                    </div>
                @endif --}}
            </div>
        </div>
    </div>

    <div x-show="!collapsed" x-cloak>
        <div class="grid grid-cols-2 gap-3 mt-6 mb-6">
            <div class="{{ $field["type"] === "fieldset" ? "" : "col-span-2" }}">
                <flux:select wire:model.live="{{ $prefix }}.type" :label="__('Type')" size="sm">
                    @foreach ($fieldtypes as $value => $label)
                        <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            @if ($field["type"] === "fieldset")
                <div class="">
                    <flux:select wire:model="{{ $prefix }}.options" :label="__('Fieldset')" placeholder="{{ __('Select a fieldset') }}" size="sm">
                        @foreach ($fieldsets as $fieldsetOption)
                            <flux:select.option value="{{ $fieldsetOption->handle }}">{{ $fieldsetOption->handle }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error ("{$prefix}.options")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    @if ($fieldsets->isEmpty())
                        <flux:text class="mt-1 block text-xs text-zinc-500">{{ __("No fieldsets yet — create one in the Fieldsets manager first.") }}</flux:text>
                    @endif
                </div>
            @else
                <div class="">
                    <flux:input wire:model="{{ $prefix }}.display" :label="__('Label')" placeholder="Title" size="sm" required x-ref="labelInput" />
                    @error ("{$prefix}.display")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>
                <div class="">
                    <div class="flex items-end gap-1">
                        <div class="flex-1">
                            <flux:input wire:model="{{ $prefix }}.handle" :label="__('Handle')" placeholder="title" size="sm" required x-ref="handleInput" />
                        </div>
                        <flux:button
                            size="sm"
                            variant="ghost"
                            icon="arrow-path"
                            type="button"
                            class="cursor-pointer"
                            title="{{ __('Regenerate from label') }}"
                            x-on:click="
                                const handleEl = $refs.handleInput.tagName === 'INPUT' ? $refs.handleInput : $refs.handleInput.querySelector('input');
                                const labelEl = $refs.labelInput.tagName === 'INPUT' ? $refs.labelInput : $refs.labelInput.querySelector('input');
                                handleEl.value = labelEl.value
                                    .toLowerCase()
                                    .replace(/[^a-z\s_]/g, '')
                                    .trim()
                                    .replace(/\s+/g, '_')
                                    .replace(/_+/g, '_');
                                handleEl.dispatchEvent(new Event('input'));
                            "
                        />
                    </div>
                    @error ("{$prefix}.handle")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="flex flex-wrap items-center gap-4 justify-start">
                    <flux:checkbox wire:model.live="{{ $prefix }}.required" :label="__('Required')" />
                    <flux:checkbox wire:model.live="{{ $prefix }}.sometimes" :label="__('Sometimes')" />
                    <flux:checkbox wire:model.live="{{ $prefix }}.localizable" :label="__('Localizable')" />
                </div>
            @endif
        </div>

        @if ($field["type"] === "select" || $field["type"] === "radio")
            <div class="mt-3">
                <flux:input wire:model.live="{{ $prefix }}.options" :label="__('Options (comma separated)')" placeholder="draft, published, archived" size="sm" />
            </div>
        @elseif ($field["type"] === "checkbox")
            <div class="mt-3 grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <flux:input wire:model.live="{{ $prefix }}.options" :label="__('Options (comma separated)')" placeholder="draft, published, archived" size="sm" />
                </div>

                <div>
                    <flux:input type="number" min="0" wire:model="{{ $prefix }}.min_items" :label="__('Min selections')" placeholder="{{ __('No minimum') }}" size="sm" />
                    @error ("{$prefix}.min_items")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:input type="number" min="0" wire:model="{{ $prefix }}.max_items" :label="__('Max selections')" placeholder="{{ __('No maximum') }}" size="sm" />
                    @error ("{$prefix}.max_items")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>
        @elseif ($field["type"] === "assets")
            <div class="mt-3 grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <flux:select wire:model="{{ $prefix }}.options" :label="__('Asset container')" placeholder="{{ __('Select a container') }}" size="sm">
                        @foreach ($assetContainers as $container)
                            <flux:select.option value="{{ $container->handle }}">{{ $container->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error ("{$prefix}.options")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:input type="number" min="0" wire:model="{{ $prefix }}.min_items" :label="__('Min items')" placeholder="{{ __('No minimum') }}" size="sm" />
                    @error ("{$prefix}.min_items")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:input type="number" min="0" wire:model="{{ $prefix }}.max_items" :label="__('Max items')" placeholder="{{ __('No maximum') }}" size="sm" />
                    @error ("{$prefix}.max_items")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>
        @elseif ($field["type"] === "link")
            <div class="mt-3">
                <flux:input wire:model.live="{{ $prefix }}.options" :label="__('Allowed collections (comma separated, blank = any)')" placeholder="pages, blog" size="sm" />
                @error ("{$prefix}.options")
                    <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror
                @if ($linkableCollections->isNotEmpty())
                    <flux:text class="mt-1 block text-xs text-zinc-500"> {{ __("Available: :handles", ["handles" => $linkableCollections->pluck("handle")->implode(", ")]) }} </flux:text>
                @endif
            </div>
        @elseif ($field["type"] === "taxonomy")
            <div class="mt-3 grid grid-cols-3 gap-3">
                <div class="col-span-1">
                    <flux:select wire:model="{{ $prefix }}.options" :label="__('Taxonomy')" placeholder="{{ __('Select a taxonomy') }}" size="sm">
                        @foreach ($taxonomies as $taxonomyOption)
                            <flux:select.option value="{{ $taxonomyOption->handle }}">{{ $taxonomyOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    @error ("{$prefix}.options")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                    @if ($taxonomies->isEmpty())
                        <flux:text class="mt-1 block text-xs text-zinc-500">{{ __("No taxonomies yet — create one in the Taxonomies manager first.") }}</flux:text>
                    @endif
                </div>

                <div>
                    <flux:input type="number" min="0" wire:model="{{ $prefix }}.min_items" :label="__('Min selections')" placeholder="{{ __('No minimum') }}" size="sm" />
                    @error ("{$prefix}.min_items")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div>
                    <flux:input type="number" min="0" wire:model="{{ $prefix }}.max_items" :label="__('Max selections')" placeholder="{{ __('No maximum') }}" size="sm" />
                    @error ("{$prefix}.max_items")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>
            </div>
        @elseif ($field["type"] === "bard")
            <div class="mt-3">
                <flux:checkbox.group wire:model="{{ $prefix }}.toolbar" :label="__('Toolbar buttons')" :description="__('Leave all unchecked to show every button.')">
                    @foreach ($bardToolbarButtons as $buttonKey => $buttonLabel)
                        <flux:checkbox value="{{ $buttonKey }}" label="{{ $buttonLabel }}" />
                    @endforeach
                </flux:checkbox.group>
            </div>
        @elseif ($field["type"] === "replicator")
            <div class="mt-3 grid grid-cols-3 gap-3">
                <div>
                    <flux:input type="number" min="0" wire:model="{{ $prefix }}.max_items" :label="__('Max rows')" placeholder="{{ __('No maximum') }}" size="sm" />
                    @error ("{$prefix}.max_items")
                        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                    @enderror
                </div>

                <div class="col-span-2 flex items-end pb-1.5">
                    <flux:switch wire:model="{{ $prefix }}.collapsed_by_default" :label="__('Collapse rows by default')" />
                </div>
            </div>
        @endif

        @unless ($field["type"] === "fieldset")
            <div class="mt-3 grid grid-cols-4 gap-3">
                @if ($field["type"] === "replicator" || $field["type"] === "assets" || $field["type"] === "link" || $field["type"] === "taxonomy" || $field["type"] === "bard" || $field["type"] === "author")
                    <flux:input :label="__('Placeholder')" placeholder="{{ __('Not applicable for this type') }}" size="sm" disabled />
                    <flux:input :label="__('Default value')" placeholder="{{ __('Not applicable for this type') }}" size="sm" disabled />
                @else
                    @if ($field["type"] === "toggle" || $field["type"] === "date")
                        <flux:input :label="__('Placeholder')" placeholder="{{ __('Not applicable for this type') }}" size="sm" disabled />
                    @else
                        <flux:input wire:model="{{ $prefix }}.placeholder" :label="__('Placeholder')" placeholder="{{ __('e.g. Enter a title') }}" size="sm" />
                    @endif

                    @if ($field["type"] === "toggle")
                        <flux:radio.group wire:key="{{ $field['_uid'] }}-default" wire:model.live="{{ $prefix }}.default" :label="__('Default value')">
                            <flux:radio value="true" label="{{ __('True') }}" />
                            <flux:radio value="false" label="{{ __('False') }}" />
                        </flux:radio.group>
                    @elseif ($field["type"] === "select" || $field["type"] === "radio")
                        <flux:select wire:model="{{ $prefix }}.default" :label="__('Default value')" placeholder="{{ __('None') }}" size="sm">
                            @foreach (collect(explode(",", $field["options"] ?? ""))->map(fn(string $option): string => trim($option))->filter() as $option)
                                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @elseif ($field["type"] === "checkbox")
                        <flux:input wire:model="{{ $prefix }}.default" :label="__('Default value(s)')" placeholder="{{ __('Comma separated, from the options above') }}" size="sm" />
                    @elseif ($field["type"] === "date")
                        <flux:input type="date" wire:model="{{ $prefix }}.default" :label="__('Default value')" size="sm" />
                    @else
                        <flux:input wire:model="{{ $prefix }}.default" :label="__('Default value')" size="sm" />
                    @endif
                @endif

                <flux:input wire:model="{{ $prefix }}.validation" :label="__('Validation rules')" placeholder="max:255|email" size="sm" />
                <flux:input wire:model="{{ $prefix }}.instructions" :label="__('Instructions')" placeholder="{{ __('Help text shown to editors') }}" size="sm" />
            </div>

            <div class="mt-3 flex gap-3 items-center rounded-md bg-gray-100 border border-gray-300 p-2 dark:bg-zinc-800/50 dark:border-zinc-600">
                <flux:select wire:model.live="{{ $prefix }}.condition_mode" :label="__('Condition')" size="sm">
                    <flux:select.option value="always">{{ __("Always show") }}</flux:select.option>
                    <flux:select.option value="show">{{ __("Show when") }}</flux:select.option>
                    <flux:select.option value="hide">{{ __("Hide when") }}</flux:select.option>
                </flux:select>

                @if (($field["condition_mode"] ?? "always") !== "always")
                    <flux:select wire:model="{{ $prefix }}.condition_field" :label="__('Field')" placeholder="{{ __('Select a field') }}" size="sm">
                        @foreach ($siblings as $otherIndex => $otherField)
                            @continue ($otherIndex === $index || ($otherField["handle"] ?? "") === "")
                            <flux:select.option value="{{ $otherField['handle'] }}">{{ $otherField["handle"] }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select wire:model="{{ $prefix }}.condition_operator" :label="__('Operator')" size="sm">
                        @foreach ($conditionOperators as $value => $label)
                            <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="{{ $prefix }}.condition_value" :label="__('Value')" size="sm" />
                @endif
            </div>
        @endunless

        @if ($field["type"] === "replicator")
            <div class="mt-3 space-y-2 rounded-md border border-dashed border-zinc-300 p-3 dark:border-zinc-600">
                <div class="flex items-center justify-between">
                    <flux:text class="text-xs font-medium text-zinc-500">{{ __("Replicator fields") }}</flux:text>
                    <flux:button size="xs" variant="outline" type="button" wire:click="addReplicatorField({{ $pathJson }})">{{ __("Add field") }}</flux:button>
                </div>

                @error ("{$prefix}.replicator_fields")
                    <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror

                <div wire:sort="reorderFields" wire:sort:group="blueprint-fields-replicator-{{ implode('-', $path) }}" wire:sort:group-id="{{ json_encode(['parentPath' => $path]) }}" class="space-y-2">
                    @foreach ($field["replicator_fields"] ?? [] as $subIndex => $subField)
                        @include ("blueprints::livewire.partials.field-editor",
                            [
                                "field" => $subField,
                                "path" => [...$path, $subIndex],
                                "prefix" => "{$prefix}.replicator_fields.{$subIndex}",
                                "index" => $subIndex,
                                "isLast" => $subIndex === count($field["replicator_fields"]) - 1,
                                "siblings" => $field["replicator_fields"],
                                "fieldtypes" => $fieldtypes,
                                "conditionOperators" => $conditionOperators,
                                "assetContainers" => $assetContainers,
                                "linkableCollections" => $linkableCollections,
                                "fieldsets" => $fieldsets,
                                "taxonomies" => $taxonomies,
                                "bardToolbarButtons" => $bardToolbarButtons
                            ])
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>
