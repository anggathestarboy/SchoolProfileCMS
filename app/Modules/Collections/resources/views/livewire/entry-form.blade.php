<section class="w-full max-w-8xl space-y-6" x-on:keydown.window.ctrl.s.prevent="$wire.save()" x-on:keydown.window.meta.s.prevent="$wire.save()">
    @php
        $preview = $this->collection->livePreview();
    @endphp

    <div class="">
        {{-- <flux:link :href="route('collections.entries', $collection->handle)" variant="subtle" wire:navigate class="text-sm">{{ __("← :collection entries", ["collection" => $collection->title]) }}</flux:link> --}}
        <div class="flex gap-3 items-center border border-gray-200 dark:border-zinc-500/80 py-2 px-3.5 rounded-xl">
            @if ($status == "published")
                <div class="w-2.5 h-6 bg-emerald-500 rounded-full"></div>
            @elseif ($status == "scheduled")
                <div class="w-2.5 h-6 bg-blue-500 rounded-full"></div>
            @elseif ($status == "need_review")
                <div class="w-2.5 h-6 bg-amber-500 rounded-full"></div>
            @elseif ($status == "draft")
                <div class="w-2.5 h-6 bg-gray-500 rounded-full"></div>
            @endif
            <flux:heading size="xl">{{ $editingId ? $title : __("New Entry") }}</flux:heading>
        </div>
    </div>

    @if ($errors->any())
        <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Please fix the following before saving:')">
            <ul class="list-disc space-y-1 pl-4 text-sm">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </flux:callout>
    @endif

    @php
        $mainTabs = collect($this->blueprintTabs)->reject(fn($tab) => $tab["handle"] === "sidebar")->values();
        $sidebarTab = collect($this->blueprintTabs)->firstWhere("handle", "sidebar");
        $sidebarFields = collect($this->blueprintFields())->where("tab", "sidebar");
        $sidebarSections = $sidebarTab["sections"] ?? [];
        $schemaEnabled = $this->collection->schemaEnabled();
        $seoEnabled = $this->collection->seoEnabled();
        $conditionTargets = $this->conditionTargetHandles($this->blueprintFields());
    @endphp

    <form wire:submit="save" class="flex flex-col md:flex-row items-start gap-7">
        <div class="w-full lg:w-[60%] xl:w-[70%] space-y-6">
            @if ($mainTabs->count() > 1 || $schemaEnabled || $seoEnabled)
                <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
                    @foreach ($mainTabs as $tab)
                        <button type="button" wire:click="setActiveTab('{{ $tab['handle'] }}')" class="border-b-2 px-3 py-2 text-sm {{ $activeTab === $tab['handle'] ? 'border-zinc-800 font-medium text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 cursor-pointer' }}">{{ $tab["display"] }}</button>
                    @endforeach
                    @if ($seoEnabled)
                        <button type="button" wire:click="setActiveTab('seo')" class="border-b-2 px-3 py-2 text-sm {{ $activeTab === 'seo' ? 'border-zinc-800 font-medium text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 cursor-pointer' }}">{{ __("SEO") }}</button>
                    @endif
                    @if ($schemaEnabled)
                        <button type="button" wire:click="setActiveTab('schema')" class="border-b-2 px-3 py-2 text-sm {{ $activeTab === 'schema' ? 'border-zinc-800 font-medium text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300 cursor-pointer' }}">{{ __("Schema") }}</button>
                    @endif
                </div>
            @endif

            @if ($schemaEnabled && $activeTab === "schema")
                <flux:text class="text-sm text-zinc-500">{{ __("Schema.org JSON-LD blocks for this entry specifically — rendered alongside the site-wide blocks from Site Settings.") }}</flux:text>

                <div class="space-y-4">
                    @forelse ($schemaBlocks as $index => $block)
                        @include ("site-settings::livewire.partials.schema-block", ["index" => $index, "block" => $block])
                    @empty
                        <flux:text class="text-zinc-500">{{ __("No schema blocks yet.") }}</flux:text>
                    @endforelse
                </div>

                <flux:button size="sm" type="button" wire:click="addSchemaBlock">{{ __("Add schema") }}</flux:button>
            @elseif ($seoEnabled && $activeTab === "seo")
                @include ("collections::livewire.partials.seo-fields", ["seo" => $seo])
            @else
                @php
                    $activeTabDef = collect($this->blueprintTabs)->firstWhere("handle", $activeTab);
                    $tabSections = $activeTabDef["sections"] ?? [];
                    $tabFields = collect($this->blueprintFields())->filter(fn($field) => ($field["tab"] ?? "main") === $activeTab);
                    $ungroupedFields = $tabFields->filter(fn($field) => ($field["section"] ?? "") === "");
                @endphp

                @forelse ($ungroupedFields as $field)
                    @if ($this->fieldIsVisible($field, $data))
                        <div>
                            @if (!($field["localizable"] ?? false) && !$this->isOriginLocale())
                                <flux:field>
                                    <flux:label
                                        >{{ $field["display"] }}
                                        @if ($field["required"] ?? false)
                                            <span class="text-red-500">*</span>
                                        @endif
                                    </flux:label>
                                    @if ($field["type"] === "bard" && is_scalar($data[$field["handle"]] ?? null) && filled($data[$field["handle"]]))
                                        <div
                                            class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 max-w-none [&_h1]:mb-1 [&_h1]:mt-3 [&_h1]:text-xl [&_h1]:font-bold [&_h2]:mb-1 [&_h2]:mt-3 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mb-1 [&_h3]:mt-3 [&_h3]:text-base [&_h3]:font-semibold [&_h4]:mb-1 [&_h4]:mt-2 [&_h4]:text-base [&_h4]:font-semibold [&_h5]:mb-1 [&_h5]:mt-2 [&_h5]:text-sm [&_h5]:font-semibold [&_h6]:mb-1 [&_h6]:mt-2 [&_h6]:text-sm [&_h6]:font-semibold [&_p]:my-1 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_blockquote]:my-1 [&_blockquote]:border-l-2 [&_blockquote]:border-zinc-300 [&_blockquote]:pl-3 [&_blockquote]:italic dark:[&_blockquote]:border-zinc-600 [&_a]:text-blue-600 [&_a]:underline dark:[&_a]:text-blue-400 [&_img]:my-2 [&_img]:max-w-full [&_img]:max-h-[350px] [&_img]:w-full [&_img]:bg-gray-200 [&_img]:object-contain [&_img]:rounded-lg"
                                        >
                                            {!! $data[$field["handle"]] !!}
                                        </div>
                                    @else
                                        <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">{{ is_scalar($data[$field["handle"]] ?? null) ? ($data[$field["handle"]] ?: "—") : __("Set in the default language") }}</div>
                                    @endif
                                    <flux:description>{{ __("Same for every language — edit it from the default language.") }}</flux:description>
                                </flux:field>
                            @else
                                @include ("collections::livewire.partials.entry-field",
                                    [
                                        "field" => $field,
                                        "path" => [$field["handle"]],
                                        "rows" => $field["type"] === "replicator" ? $data[$field["handle"]] ?? [] : null,
                                        "assetIds" => $field["type"] === "assets" ? $data[$field["handle"]] ?? [] : null,
                                        "linkValue" => $field["type"] === "link" ? $data[$field["handle"]] ?? "" : null,
                                        "taxonomyIds" => $field["type"] === "taxonomy" ? $data[$field["handle"]] ?? [] : null,
                                        "bardValue" => $field["type"] === "bard" ? $data[$field["handle"]] ?? "" : null,
                                        "preview" => $this->collection->livePreview(),
                                        "forceLive" => in_array($field["handle"], $conditionTargets, true)
                                    ])
                            @endif
                        </div>
                    @endif
                @empty
                    @if ($tabFields->isEmpty())
                        <flux:text class="text-zinc-500"> {{ count($this->blueprintFields()) === 0 ? __("This blueprint has no fields yet.") : __("No fields in this tab.") }} </flux:text>
                    @endif
                @endforelse

                @foreach ($tabSections as $section)
                    @php $sectionFields = $tabFields->filter(fn($field) => ($field["section"] ?? "") === $section["handle"]); @endphp
                    @if ($sectionFields->isNotEmpty())
                        <div class="px-2 py-2.5 rounded-2xl bg-gray-200/80 dark:bg-zinc-950">
                            <div class="font-semibold text-black px-2">
                                <flux:subheading>{{ $section["display"] }}</flux:subheading>
                            </div>

                            <div class="space-y-6 bg-white dark:bg-zinc-800 p-3 rounded-lg mt-3">
                                @foreach ($sectionFields as $field)
                                    @if ($this->fieldIsVisible($field, $data))
                                        <div>
                                            @if (!($field["localizable"] ?? false) && !$this->isOriginLocale())
                                                <flux:field>
                                                    <flux:label
                                                        >{{ $field["display"] }}
                                                        @if ($field["required"] ?? false)
                                                            <span class="text-red-500">*</span>
                                                        @endif
                                                    </flux:label>
                                                    @if ($field["type"] === "bard" && is_scalar($data[$field["handle"]] ?? null) && filled($data[$field["handle"]]))
                                                        <div
                                                            class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-800 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-200 max-w-none [&_h1]:mb-1 [&_h1]:mt-3 [&_h1]:text-xl [&_h1]:font-bold [&_h2]:mb-1 [&_h2]:mt-3 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mb-1 [&_h3]:mt-3 [&_h3]:text-base [&_h3]:font-semibold [&_h4]:mb-1 [&_h4]:mt-2 [&_h4]:text-base [&_h4]:font-semibold [&_h5]:mb-1 [&_h5]:mt-2 [&_h5]:text-sm [&_h5]:font-semibold [&_h6]:mb-1 [&_h6]:mt-2 [&_h6]:text-sm [&_h6]:font-semibold [&_p]:my-1 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_blockquote]:my-1 [&_blockquote]:border-l-2 [&_blockquote]:border-zinc-300 [&_blockquote]:pl-3 [&_blockquote]:italic dark:[&_blockquote]:border-zinc-600 [&_a]:text-blue-600 [&_a]:underline dark:[&_a]:text-blue-400 [&_img]:my-2 [&_img]:max-w-full [&_img]:max-h-[350px] [&_img]:w-full [&_img]:bg-gray-200 [&_img]:object-contain [&_img]:rounded-lg"
                                                        >
                                                            {!! $data[$field["handle"]] !!}
                                                        </div>
                                                    @else
                                                        <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">{{ is_scalar($data[$field["handle"]] ?? null) ? ($data[$field["handle"]] ?: "—") : __("Set in the default language") }}</div>
                                                    @endif
                                                    <flux:description>{{ __("Same for every language — edit it from the default language.") }}</flux:description>
                                                </flux:field>
                                            @else
                                                @include ("collections::livewire.partials.entry-field",
                                                    [
                                                        "field" => $field,
                                                        "path" => [$field["handle"]],
                                                        "rows" => $field["type"] === "replicator" ? $data[$field["handle"]] ?? [] : null,
                                                        "assetIds" => $field["type"] === "assets" ? $data[$field["handle"]] ?? [] : null,
                                                        "linkValue" => $field["type"] === "link" ? $data[$field["handle"]] ?? "" : null,
                                                        "taxonomyIds" => $field["type"] === "taxonomy" ? $data[$field["handle"]] ?? [] : null,
                                                        "bardValue" => $field["type"] === "bard" ? $data[$field["handle"]] ?? "" : null,
                                                        "forceLive" => in_array($field["handle"], $conditionTargets, true)
                                                    ])
                                            @endif
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            @endif
        </div>

        <div class="w-full lg:w-[40%] xl:w-[30%] col-span-1 space-y-5 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
            <div class="">
                <div class="flex justify-between gap-2 pb-2">
                    <flux:button variant="primary" class="w-full" type="submit">{{ __("Save") }}</flux:button>
                    <flux:button :href="route('collections.entries', $collection->handle)" wire:navigate variant="filled">{{ __("Cancel") }}</flux:button>
                </div>

                <div class="flex gap-2">
                    @if ($this->collection->livePreview() && $this->previewUrl)
                        <flux:button size="sm" icon="eye" :href="$this->previewUrl" target="_blank" class="w-full">{{ __("Live Preview") }}</flux:button>
                    @endif

                    @if ($this->resolvedUrl)
                        @if ($status === "published")
                            <flux:button size="sm" icon="arrow-top-right-on-square" href="{{ $this->resolvedUrl }}" target="_blank" class="w-full">{{ __("Visit") }}</flux:button>
                        @else
                            <flux:button size="sm" icon="arrow-top-right-on-square" disabled class="w-full">{{ __("Visit") }}</flux:button>
                        @endif
                    @endif
                </div>
            </div>

            @if (count($this->siteLinks) > 1)
                <div class="border border-gray-300 p-2 rounded-xl dark:border-zinc-500">
                    {{-- <flux:text class="text-xs font-medium text-zinc-500">{{ __("Site") }}</flux:text> --}}
                    <div class="mt-1 flex flex-col gap-1">
                        @foreach ($this->siteLinks as $siteLink)
                            @if ($siteLink["active"])
                                <flux:button size="sm" variant="primary" disabled>{{ $siteLink["name"] }}</flux:button>
                            @else
                                <flux:button size="sm" variant="filled" :href="$siteLink['href']" wire:navigate>{{ $siteLink["name"] }}</flux:button>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endif

            <flux:field>
                <flux:label>{{ __("Title") }} <span class="text-red-500">*</span></flux:label>
                @if ($preview)
                    <flux:input wire:model.live.debounce.600ms="title" placeholder="{{ __('Entry title') }}" maxlength="300" required size="sm" />
                @else
                    <flux:input wire:model="title" placeholder="{{ __('Entry title') }}" maxlength="300" required size="sm" />
                @endif
            </flux:field>

            <flux:field>
                <flux:label>{{ __("Slug") }} <span class="text-red-500">*</span></flux:label>
                <div class="grid grid-cols-[1fr_auto] items-end gap-2">
                    @if ($preview)
                        <flux:input wire:model.live.debounce.600ms="slug" placeholder="enter-slug" class="min-w-0 w-full" maxlength="255" required size="sm" />
                    @else
                        <flux:input wire:model="slug" placeholder="enter-slug" class="min-w-0 w-full" maxlength="255" required size="sm" />
                    @endif
                    <flux:button size="sm" type="button" icon="arrow-path" wire:click="regenerateSlug" :tooltip="__('Regenerate from title')" />
                </div>
            </flux:field>

            {{-- @if ($this->availableBlueprints->count() > 1)
                <flux:select wire:model.live="blueprintHandle" :label="__('Blueprint')" size="sm">
                    @foreach ($this->availableBlueprints as $availableBlueprint)
                        <flux:select.option value="{{ $availableBlueprint->handle }}">{{ $availableBlueprint->handle }}</flux:select.option>
                    @endforeach
                </flux:select>
            @else
                <div>
                    <flux:text class="text-xs font-medium text-zinc-500">{{ __("Blueprint") }}</flux:text>
                    <flux:text>{{ $blueprintHandle }}</flux:text>
                </div>
            @endif --}}

            @if ($collection->structured())
                <flux:select wire:model="parentId" :label="__('Parent')" size="sm">
                    <flux:select.option value="">{{ __("None — top level") }}</flux:select.option>
                    @foreach ($this->availableParentEntries as $parentEntry)
                        <flux:select.option value="{{ $parentEntry->id }}">{{ $parentEntry->data["title"] ?? ($parentEntry->slug ?? $parentEntry->id) }}</flux:select.option>
                    @endforeach
                </flux:select>
                @error ("parentId")
                    <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
                @enderror
            @endif

            {{-- Hidden when the collection's "Visible Order" setting is off — ordering (and the "first entry becomes the homepage" logic) still works either way, see Collection::visibleOrder(). --}}
            @if ($collection->visibleOrder())
                @if ($preview)
                    <flux:input type="number" wire:model.live.debounce.600ms="order" :label="__('Order')" placeholder="1" size="sm" />
                @else
                    <flux:input type="number" wire:model="order" :label="__('Order')" placeholder="1" size="sm" />
                @endif
            @endif

            <flux:field>
                <flux:label>{{ __("Date") }} <span class="text-red-500">*</span></flux:label>
                @if ($preview)
                    <flux:input type="datetime-local" wire:model.live.debounce.600ms="entryDate" required size="sm" />
                @else
                    <flux:input type="datetime-local" wire:model="entryDate" required size="sm" />
                @endif
            </flux:field>

            <flux:field>
                <flux:label>{{ __("Status") }} <span class="text-red-500">*</span></flux:label>
                {{--
                    Picking "Published" (or "Scheduled") with a Date in the
                    future saves it as "Scheduled" regardless of which of
                    the two was picked — see EntryForm::save() — until
                    entries:publish-scheduled flips it live once that date
                    passes.
                --}}
                @if ($preview)
                    <flux:radio.group wire:model.live="status" class="">
                        @foreach ($statuses as $value => $label)
                            <flux:radio value="{{ $value }}" label="{{ $label }}" :disabled="! $canEditStatus" />
                        @endforeach
                    </flux:radio.group>
                @else
                    <flux:radio.group wire:model="status" class="">
                        @foreach ($statuses as $value => $label)
                            <flux:radio value="{{ $value }}" label="{{ $label }}" :disabled="! $canEditStatus" />
                        @endforeach
                    </flux:radio.group>
                @endif
            </flux:field>

            @include ("collections::livewire.partials.sidebar-fields", ["sidebarFields" => $sidebarFields, "sidebarSections" => $sidebarSections, "data" => $data, "preview" => $preview])
        </div>
    </form>

    @livewire ("media::asset-picker")
</section>
