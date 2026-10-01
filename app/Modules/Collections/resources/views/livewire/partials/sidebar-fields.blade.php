{{--
    Renders a blueprint's "sidebar" tab (handle exactly "sidebar" — see
    BlueprintForm's special badge for it) inside the right-hand sidebar
    column of a blueprint-driven form, instead of the main tabbed content
    area. Shared by Collections/entry-form, Taxonomies/term-form and
    Globals/global-set-form, which all have that same left-content +
    right-sidebar layout.

    Expects: $sidebarFields (top-level fields whose "tab" is "sidebar"),
    $sidebarSections (that tab's "sections" list), $data, $preview (bool).
--}}
@php
    $conditionTargets = $this->conditionTargetHandles($this->blueprintFields());
@endphp
@if ($sidebarFields->isNotEmpty())
    <div class="space-y-6 border-t border-zinc-200 pt-5 dark:border-zinc-700">
        @foreach ($sidebarFields->where("section", "") as $field)
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
                                "preview" => $preview ?? false,
                                "forceLive" => in_array($field["handle"], $conditionTargets, true)
                            ])
                    @endif
                </div>
            @endif
        @endforeach

        @foreach ($sidebarSections as $section)
            @php $sectionFields = $sidebarFields->where("section", $section["handle"]); @endphp
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
                                                "preview" => $preview ?? false,
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
    </div>
@endif
