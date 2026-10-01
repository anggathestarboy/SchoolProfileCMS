<section class="w-full max-w-7xl space-y-6" x-data="{ deleteFieldPath: null, deleteTabIndex: null, deleteSectionTabIndex: null, deleteSectionIndex: null }" x-on:keydown.window.ctrl.s.prevent="$wire.save()" x-on:keydown.window.meta.s.prevent="$wire.save()">
    <div class="flex items-center justify-start gap-2 border py-2 px-4 rounded-xl dark:border-zinc-500">
        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m13.46 8.291 3.849-3.849a1.5 1.5 0 0 1 2.122 0l.127.127a1.5 1.5 0 0 1 0 2.122l-3.84 3.838a4 4 0 0 0-2.258-2.238Zm0 0a4 4 0 0 1 2.263 2.238l3.662-3.662a8.961 8.961 0 0 1 0 10.27l-3.676-3.676m-2.25-5.17 3.678-3.676a8.961 8.961 0 0 0-10.27 0l3.662 3.662a4 4 0 0 0-2.238 2.258L4.615 6.863a8.96 8.96 0 0 0 0 10.27l3.662-3.662a4 4 0 0 0 2.258 2.238l-3.672 3.676a8.96 8.96 0 0 0 10.27 0l-3.662-3.662a4.001 4.001 0 0 0 2.238-2.262m0 0 3.849 3.848a1.5 1.5 0 0 1 0 2.122l-.127.126a1.499 1.499 0 0 1-2.122 0l-3.838-3.838a4 4 0 0 0 2.238-2.258Zm.29-1.461a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm-7.718 1.471-3.84 3.838a1.5 1.5 0 0 0 0 2.122l.128.126a1.5 1.5 0 0 0 2.122 0l3.848-3.848a4 4 0 0 1-2.258-2.238Zm2.248-5.19L6.69 4.442a1.5 1.5 0 0 0-2.122 0l-.127.127a1.5 1.5 0 0 0 0 2.122l3.849 3.848a4 4 0 0 1 2.238-2.258Z" />
        </svg>

        <flux:heading size="xl">{{ $editingId ? __("Edit blueprint") : __("New blueprint") }}</flux:heading>
    </div>

    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-4">
            @if ($this->availableNamespaces->isEmpty())
                <div>
                    <flux:text class="text-sm font-medium">{{ __("Namespace") }}</flux:text>
                    <flux:text class="text-zinc-500">{{ __("No collections yet — create one in the Collections module first.") }}</flux:text>
                </div>
            @else
                <flux:select wire:model="namespace" :label="__('Namespace')" placeholder="{{ __('No namespace') }}">
                    @foreach ($this->availableNamespaces as $availableNamespace)
                        <flux:select.option value="{{ $availableNamespace['value'] }}">{{ $availableNamespace["label"] }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif

            <flux:input wire:model="handle" :label="__('Handle')" required />

            @if (empty($templates))
                <div>
                    <flux:text class="text-sm font-medium">{{ __("Template") }}</flux:text>
                    <flux:text class="text-zinc-500">{{ __("No templates yet — add a .blade.php file under resources/views/pages first.") }}</flux:text>
                </div>
            @else
                <flux:select wire:model="template" :label="__('Template')" placeholder="{{ __('No template') }}" :description="__('The Blade view used to render entries using this blueprint.')">
                    @foreach ($templates as $availableTemplate)
                        <flux:select.option value="{{ $availableTemplate }}">{{ $availableTemplate }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endif
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between bg-white z-10 sticky top-0 p-2 border rounded-b-xl dark:bg-zinc-900 dark:border-zinc-700">
                <flux:switch wire:model.live="fieldsCollapsedByDefault" size="sm" :label="__('Collapse fields by default')" />
                <div class="flex gap-2">
                    <flux:button size="xs" variant="ghost" type="button" wire:click="$dispatch('collapse-all-fields')">{{ __("Collapse all") }}</flux:button>
                    <flux:button size="xs" variant="ghost" type="button" wire:click="$dispatch('expand-all-fields')">{{ __("Expand all") }}</flux:button>
                    <flux:button size="xs" type="button" wire:click="addTab">{{ __("Add tab") }}</flux:button>
                </div>
            </div>

            @error ("fields")
                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror

            @foreach ($tabs as $tabIndex => $tab)
                @php
                    $ungroupedFieldIndexes = collect($fields)->keys()->filter(fn($i) => ($fields[$i]["tab"] ?? "main") === $tab["handle"] && ($fields[$i]["section"] ?? "") === "")->values();
                @endphp

                <div class="space-y-3 rounded-xl border border-gray-200 p-2 dark:border-gray-700 bg-gray-200 dark:bg-zinc-950">
                    <div class="bg-white p-3 rounded-xl dark:bg-zinc-800">
                        @if ($tabIndex !== 0 && $editingTabIndex === $tabIndex)
                            <div class="flex items-center gap-3 mb-3">
                                <flux:input wire:model="tabs.{{ $tabIndex }}.display" placeholder="{{ __('Label') }}" size="sm" class="w-28" x-ref="tabLabelInput" />
                                <flux:input wire:model="tabs.{{ $tabIndex }}.handle" placeholder="handle" size="sm" class="w-28" x-ref="tabHandleInput" />
                                <flux:button
                                    size="sm"
                                    variant="ghost"
                                    icon="arrow-path"
                                    type="button"
                                    title="{{ __('Regenerate from label') }}"
                                    x-on:click="
                                        const handleEl = $refs.tabHandleInput.tagName === 'INPUT' ? $refs.tabHandleInput : $refs.tabHandleInput.querySelector('input');
                                        const labelEl = $refs.tabLabelInput.tagName === 'INPUT' ? $refs.tabLabelInput : $refs.tabLabelInput.querySelector('input');
                                        handleEl.value = labelEl.value
                                            .toLowerCase()
                                            .replace(/[^a-z\s_]/g, '')
                                            .trim()
                                            .replace(/\s+/g, '_')
                                            .replace(/_+/g, '_');
                                        handleEl.dispatchEvent(new Event('input'));
                                    "
                                />
                                <flux:button size="sm" variant="filled" type="button" wire:click="cancelTab({{ $tabIndex }})">{{ __("Cancel") }}</flux:button>
                                <flux:button size="sm" variant="primary" type="button" wire:click="confirmTab">{{ __("Done") }}</flux:button>
                            </div>
                            @error ("tabs.{$tabIndex}.handle")
                                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                            @enderror
                        @else
                            <div class="flex items-center justify-between mb-2">
                                <div class="flex items-center gap-1">
                                    @if ($tabIndex !== 0)
                                        <flux:dropdown>
                                            <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" />
                                            <flux:menu>
                                                <flux:menu.item icon="pencil" wire:click="$set('editingTabIndex', {{ $tabIndex }})">{{ __("Edit") }}</flux:menu.item>
                                                <flux:modal.trigger name="confirm-delete-tab">
                                                    <flux:menu.item icon="trash" variant="danger" x-on:click="deleteTabIndex = {{ $tabIndex }}">{{ __("Delete") }}</flux:menu.item>
                                                </flux:modal.trigger>
                                            </flux:menu>
                                        </flux:dropdown>
                                    @endif
                                    @if ($tab["handle"] === "sidebar")
                                        <span class="text-[10px] bg-indigo-300 py-0.5 px-2 rounded-2xl inline-flex items-center gap-0.5"><flux:icon.view-columns class="size-3" /> {{ __("Sidebar") }}</span>
                                    @else
                                        <span class="text-[10px] bg-orange-300 py-0.5 px-2 rounded-2xl"> Tab</span>
                                    @endif
                                    <flux:text class="font-medium">{{ $tab["display"] ?: __("Main") }}</flux:text>
                                </div>
                                <div class="flex gap-2">
                                    @if ($tab["handle"] === "sidebar")
                                        <flux:button size="xs" type="button" wire:click="addField('{{ $tab['handle'] }}')">{{ __("Add field") }}</flux:button>
                                    @else
                                        <flux:button size="xs" variant="ghost" type="button" wire:click="addSection({{ $tabIndex }})">{{ __("Add section") }}</flux:button>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div class="space-y-3" wire:sort="reorderFields" wire:sort:group="blueprint-fields" wire:sort:group-id="{{ json_encode(['tab' => $tab['handle'], 'section' => '']) }}">
                            @foreach ($ungroupedFieldIndexes as $position => $index)
                                @include ("blueprints::livewire.partials.field-editor",
                                    [
                                        "field" => $fields[$index],
                                        "path" => [$index],
                                        "prefix" => "fields.{$index}",
                                        "index" => $index,
                                        "isFirst" => $position === 0,
                                        "isLast" => $position === count($ungroupedFieldIndexes) - 1,
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

                        @if ($ungroupedFieldIndexes->isEmpty() && empty($tab["sections"]))
                            <flux:text class="text-sm text-zinc-500">{{ __("No fields in this tab yet.") }}</flux:text>
                        @endif

                        <div class="space-y-3" wire:sort="reorderSections" wire:sort:group="blueprint-sections-{{ $tabIndex }}" wire:sort:group-id="{{ $tabIndex }}">
                            @foreach ($tab["sections"] ?? [] as $sectionIndex => $section)
                                @php $sectionFieldIndexes = collect($fields)->keys()->filter(fn($i) => ($fields[$i]["tab"] ?? "main") === $tab["handle"] && trim($section["handle"] ?? "") !== "" && ($fields[$i]["section"] ?? "") === $section["handle"])->values(); @endphp

                                <div wire:key="section-{{ $tabIndex }}-{{ $sectionIndex }}" wire:sort:item="{{ $sectionIndex }}" class="space-y-3 mt-3 rounded-md border border-dashed border-zinc-300 p-3 dark:border-zinc-600">
                                    @if ($editingSection === [$tabIndex, $sectionIndex])
                                        <div class="flex items-center gap-3">
                                            <flux:input wire:model="tabs.{{ $tabIndex }}.sections.{{ $sectionIndex }}.display" required placeholder="{{ __('Label') }}" size="sm" class="w-28" x-ref="sectionLabelInput" />
                                            <flux:input wire:model="tabs.{{ $tabIndex }}.sections.{{ $sectionIndex }}.handle" required placeholder="handle" size="sm" class="w-28" x-ref="sectionHandleInput" />
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrow-path"
                                                type="button"
                                                title="{{ __('Regenerate from label') }}"
                                                x-on:click="
                                                    const handleEl = $refs.sectionHandleInput.tagName === 'INPUT' ? $refs.sectionHandleInput : $refs.sectionHandleInput.querySelector('input');
                                                    const labelEl = $refs.sectionLabelInput.tagName === 'INPUT' ? $refs.sectionLabelInput : $refs.sectionLabelInput.querySelector('input');
                                                    handleEl.value = labelEl.value
                                                        .toLowerCase()
                                                        .replace(/[^a-z\s_]/g, '')
                                                        .trim()
                                                        .replace(/\s+/g, '_')
                                                        .replace(/_+/g, '_');
                                                    handleEl.dispatchEvent(new Event('input'));
                                                "
                                            />
                                            <flux:button size="sm" variant="filled" type="button" wire:click="cancelSection({{ $tabIndex }}, {{ $sectionIndex }})">{{ __("Cancel") }}</flux:button>
                                            <flux:button size="sm" variant="primary" type="button" wire:click="confirmSection">{{ __("Done") }}</flux:button>
                                        </div>
                                        @error ("tabs.{$tabIndex}.sections.{$sectionIndex}.handle")
                                            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                                        @enderror
                                    @else
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center gap-1">
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
                                                <span class="text-[10px] bg-emerald-300 py-0.5 px-2 rounded-2xl"> Section </span>
                                                <flux:text class="text-xs font-medium text-zinc-500">{{ $section["display"] ?: $section["handle"] }}</flux:text>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <flux:dropdown>
                                                    <flux:button size="xs" variant="ghost" icon="ellipsis-vertical" />
                                                    <flux:menu>
                                                        <flux:menu.item icon="pencil" wire:click="$set('editingSection', [{{ $tabIndex }}, {{ $sectionIndex }}])">{{ __("Edit") }}</flux:menu.item>
                                                        <flux:modal.trigger name="confirm-delete-section">
                                                            <flux:menu.item icon="trash" variant="danger" x-on:click="deleteSectionTabIndex = {{ $tabIndex }}; deleteSectionIndex = {{ $sectionIndex }}">{{ __("Delete") }}</flux:menu.item>
                                                        </flux:modal.trigger>
                                                    </flux:menu>
                                                </flux:dropdown>
                                                <flux:button size="xs" variant="primary" type="button" wire:click="addField('{{ $tab['handle'] }}', '{{ $section['handle'] }}')">{{ __("Add field") }}</flux:button>
                                            </div>
                                        </div>
                                    @endif

                                    <div wire:sort="reorderFields" wire:sort:group="blueprint-fields" wire:sort:group-id="{{ json_encode(['tab' => $tab['handle'], 'section' => $section['handle']]) }}" class="space-y-3">
                                        @foreach ($sectionFieldIndexes as $position => $index)
                                            @include ("blueprints::livewire.partials.field-editor",
                                                [
                                                    "field" => $fields[$index],
                                                    "path" => [$index],
                                                    "prefix" => "fields.{$index}",
                                                    "index" => $index,
                                                    "isFirst" => $position === 0,
                                                    "isLast" => $position === count($sectionFieldIndexes) - 1,
                                                    "siblings" => $fields,
                                                    "fieldtypes" => $fieldtypes,
                                                    "conditionOperators" => $conditionOperators,
                                                    "assetContainers" => $assetContainers,
                                                    "linkableCollections" => $linkableCollections,
                                                    "bardToolbarButtons" => $bardToolbarButtons,
                                                    "tabs" => $tabs
                                                ])
                                        @endforeach
                                    </div>

                                    @if ($sectionFieldIndexes->isEmpty())
                                        <flux:text class="text-sm text-zinc-500">{{ __("No fields in this section yet.") }}</flux:text>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach

            @if ($this->schemaTabVisible)
                <div class="space-y-3 rounded-xl border border-gray-200 p-2 dark:border-gray-700 bg-gray-200 dark:bg-zinc-950">
                    <div class="bg-white p-3 rounded-xl dark:bg-zinc-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <span class="text-[10px] bg-indigo-300 py-0.5 px-2 rounded-2xl inline-flex items-center gap-0.5"><flux:icon.code-bracket class="size-3" /> {{ __("Code-managed") }}</span>
                                <flux:text class="font-medium">{{ __("Schema") }}</flux:text>
                            </div>
                        </div>
                        <flux:text class="text-sm text-zinc-500">{{ __("Entries in this collection get a \"Schema\" tab for attaching schema.org JSON-LD blocks. Its fields are fixed in code, not editable here — see EntryForm.") }}</flux:text>
                    </div>
                </div>
            @endif

            @if ($this->seoTabVisible)
                <div class="space-y-3 rounded-xl border border-gray-200 p-2 dark:border-gray-700 bg-gray-200 dark:bg-zinc-950">
                    <div class="bg-white p-3 rounded-xl dark:bg-zinc-800">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-1">
                                <span class="text-[10px] bg-indigo-300 py-0.5 px-2 rounded-2xl inline-flex items-center gap-0.5"><flux:icon.code-bracket class="size-3" /> {{ __("Code-managed") }}</span>
                                <flux:text class="font-medium">{{ __("SEO") }}</flux:text>
                            </div>
                        </div>
                        <flux:text class="text-sm text-zinc-500">{{ __("Entries in this collection get an \"SEO\" tab for meta title/description, robots, sitemap hints, etc. Its fields are fixed in code, not editable here — see EntryForm.") }}</flux:text>
                    </div>
                </div>
            @endif
        </div>

        <div class="flex justify-end gap-2">
            <flux:button :href="route('blueprints.index')" wire:navigate variant="filled">{{ __("Cancel") }}</flux:button>
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

    <flux:modal name="confirm-delete-tab" class="min-w-[22rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this tab?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("Its fields will move back to Main. This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" wire:click="removeTab(deleteTabIndex)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    <flux:modal name="confirm-delete-section" class="min-w-[22rem]">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __("Delete this section?") }}</flux:heading>
            <flux:text class="text-zinc-500">{{ __("Its fields will be ungrouped. This cannot be undone.") }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled">{{ __("Cancel") }}</flux:button>
                </flux:modal.close>
                <flux:modal.close>
                    <flux:button variant="danger" type="button" wire:click="removeSection(deleteSectionTabIndex, deleteSectionIndex)">{{ __("Delete") }}</flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</section>
