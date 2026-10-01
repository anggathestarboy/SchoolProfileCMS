@php
    $prefix = "data." . implode(".", $path);
    $fieldLabel = new \Illuminate\Support\HtmlString(e($field["display"]) . ($field["required"] ?? false ? ' <span class="text-red-500">*</span>' : ""));
    // Typed/selected fields sync to the server as you go — instead of only
    // on the next unrelated request — whenever the collection has Live
    // Preview enabled ($preview), or another sibling field's "show
    // when"/"hide when" condition depends on this one ($forceLive), so that
    // dependent field's visibility updates immediately instead of lagging
    // behind. Complex fields (bard, assets, replicator, taxonomy checkboxes)
    // already sync on every interaction via explicit $wire calls/clicks, so
    // they're left alone.
    $preview = $preview ?? false;
    $live = $preview || ($forceLive ?? false);
@endphp

@if ($field["type"] === "toggle")
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:switch wire:model.live="{{ $prefix }}" title="{{ $field['handle'] }}" />
        @else
            <flux:switch wire:model="{{ $prefix }}" title="{{ $field['handle'] }}" />
        @endif
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
    </flux:field>
    @error ($prefix)
        <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror
@elseif ($field["type"] === "select")
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:select wire:model.live="{{ $prefix }}" :placeholder="$field['placeholder'] ?? __('Select an option')">
                @foreach ($field["options"] ?? [] as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:select wire:model="{{ $prefix }}" :placeholder="$field['placeholder'] ?? __('Select an option')">
                @foreach ($field["options"] ?? [] as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@elseif ($field["type"] === "author")
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:select wire:model.live="{{ $prefix }}" placeholder="{{ __('Select an author') }}">
                @foreach ($this->availableAuthors() as $author)
                    <flux:select.option value="{{ $author->id }}">{{ $author->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @else
            <flux:select wire:model="{{ $prefix }}" placeholder="{{ __('Select an author') }}">
                @foreach ($this->availableAuthors() as $author)
                    <flux:select.option value="{{ $author->id }}">{{ $author->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@elseif ($field["type"] === "radio")
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:radio.group wire:model.live="{{ $prefix }}">
                @foreach ($field["options"] ?? [] as $option)
                    <flux:radio value="{{ $option }}" label="{{ $option }}" />
                @endforeach
            </flux:radio.group>
        @else
            <flux:radio.group wire:model="{{ $prefix }}">
                @foreach ($field["options"] ?? [] as $option)
                    <flux:radio value="{{ $option }}" label="{{ $option }}" />
                @endforeach
            </flux:radio.group>
        @endif
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@elseif ($field["type"] === "checkbox")
    @php
        $checkboxHint = match (true) {
            ($field["min_items"] ?? null) !== null && ($field["max_items"] ?? null) !== null => __(":min–:max selections", ["min" => $field["min_items"], "max" => $field["max_items"]]),
            ($field["min_items"] ?? null) !== null => __("At least :min selections", ["min" => $field["min_items"]]),
            ($field["max_items"] ?? null) !== null => __("Up to :max selections", ["max" => $field["max_items"]]),
            default => null,
        };
    @endphp
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:checkbox.group wire:model.live="{{ $prefix }}">
                @foreach ($field["options"] ?? [] as $option)
                    <flux:checkbox value="{{ $option }}" label="{{ $option }}" />
                @endforeach
            </flux:checkbox.group>
        @else
            <flux:checkbox.group wire:model="{{ $prefix }}">
                @foreach ($field["options"] ?? [] as $option)
                    <flux:checkbox value="{{ $option }}" label="{{ $option }}" />
                @endforeach
            </flux:checkbox.group>
        @endif
        @php $checkboxDescription = trim(($field["instructions"] ?? "") . " " . ($checkboxHint ?? "")); @endphp
        @if ($checkboxDescription !== "")
            <flux:description class="mt-0! text-xs">{{ $checkboxDescription }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@elseif ($field["type"] === "date")
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:input type="date" wire:model.live.debounce.600ms="{{ $prefix }}" />
        @else
            <flux:input type="date" wire:model="{{ $prefix }}" />
        @endif
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@elseif ($field["type"] === "number")
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:input type="number" wire:model.live.debounce.600ms="{{ $prefix }}" :placeholder="$field['placeholder']" />
        @else
            <flux:input type="number" wire:model="{{ $prefix }}" :placeholder="$field['placeholder']" />
        @endif
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@elseif ($field["type"] === "textarea" || $field["type"] === "markdown")
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:textarea wire:model.live.debounce.600ms="{{ $prefix }}" :placeholder="$field['placeholder']" rows="4" />
        @else
            <flux:textarea wire:model="{{ $prefix }}" :placeholder="$field['placeholder']" rows="4" />
        @endif
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@elseif ($field["type"] === "bard")
    @php
        $bardToolbar = $field["toolbar"] ?? [];
        $bardShowAll = empty($bardToolbar);
        $bardShows = fn(string $key): bool => $bardShowAll || in_array($key, $bardToolbar, true);
        $bardBtn = "rounded p-1.5 text-zinc-500 hover:bg-zinc-100 data-[active]:bg-zinc-200 data-[active]:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:data-[active]:bg-white/20 dark:data-[active]:text-white";
        $bardBtnWide = "rounded px-2 py-1 text-xs font-semibold text-zinc-500 hover:bg-zinc-100 data-[active]:bg-zinc-200 data-[active]:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:data-[active]:bg-white/20 dark:data-[active]:text-white";
        $bardAssetContainer = \App\Modules\Media\Models\AssetContainer::orderBy("handle")->value("handle");
    @endphp
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        <div wire:ignore wire:key="bard-{{ implode('-', $path) }}" x-data="bardEditor(@js($bardValue ?? ''), '{{ $prefix }}', @js($bardAssetContainer))" class="rounded-lg border border-zinc-200 shadow-xs dark:border-white/10">
            <div class="flex flex-wrap items-center gap-1 border-b border-zinc-200 p-1.5 dark:border-white/10">
                @if ($bardShows("bold"))
                    <button type="button" x-on:mousedown.prevent x-on:click="run('toggleBold')" :data-active="isActive('bold') || null" class="{{ $bardBtn }}"><flux:icon.bold class="size-4" /></button>
                @endif
                @if ($bardShows("italic"))
                    <button type="button" x-on:mousedown.prevent x-on:click="run('toggleItalic')" :data-active="isActive('italic') || null" class="{{ $bardBtn }}"><flux:icon.italic class="size-4" /></button>
                @endif
                @if ($bardShows("underline"))
                    <button type="button" x-on:mousedown.prevent x-on:click="run('toggleUnderline')" :data-active="isActive('underline') || null" class="{{ $bardBtn }}"><flux:icon.underline class="size-4" /></button>
                @endif
                @if ($bardShows("strike"))
                    <button type="button" x-on:mousedown.prevent x-on:click="run('toggleStrike')" :data-active="isActive('strike') || null" class="{{ $bardBtn }}"><flux:icon.strikethrough class="size-4" /></button>
                @endif

                @if ($bardShows("h1") || $bardShows("h2") || $bardShows("h3") || $bardShows("h4") || $bardShows("h5") || $bardShows("h6"))
                    <div class="mx-1 h-5 w-px bg-zinc-200 dark:bg-white/10"></div>
                @endif

                @foreach ([1, 2, 3, 4, 5, 6] as $level)
                    @if ($bardShows("h{$level}"))
                        <button type="button" x-on:mousedown.prevent x-on:click="run('toggleHeading', { level: {{ $level }} })" :data-active="isActive('heading', { level: {{ $level }} }) || null" class="{{ $bardBtnWide }}">H{{ $level }}</button>
                    @endif
                @endforeach

                @if ($bardShows("bulletlist") || $bardShows("orderedlist") || $bardShows("blockquote") || $bardShows("link") || $bardShows("image"))
                    <div class="mx-1 h-5 w-px bg-zinc-200 dark:bg-white/10"></div>
                @endif

                @if ($bardShows("bulletlist"))
                    <button type="button" x-on:mousedown.prevent x-on:click="run('toggleBulletList')" :data-active="isActive('bulletList') || null" class="{{ $bardBtn }}"><flux:icon.list-bullet class="size-4" /></button>
                @endif
                @if ($bardShows("orderedlist"))
                    <button type="button" x-on:mousedown.prevent x-on:click="run('toggleOrderedList')" :data-active="isActive('orderedList') || null" class="{{ $bardBtn }}"><flux:icon.numbered-list class="size-4" /></button>
                @endif
                @if ($bardShows("blockquote"))
                    <button type="button" x-on:mousedown.prevent x-on:click="run('toggleBlockquote')" :data-active="isActive('blockquote') || null" class="rounded px-2 py-1 text-sm text-zinc-500 hover:bg-zinc-100 data-[active]:bg-zinc-200 data-[active]:text-zinc-900 dark:text-zinc-300 dark:hover:bg-white/10 dark:data-[active]:bg-white/20 dark:data-[active]:text-white">"</button>
                @endif
                @if ($bardShows("link"))
                    <button type="button" x-on:mousedown.prevent x-on:click="setLink()" :data-active="isActive('link') || null" class="{{ $bardBtn }}"><flux:icon.link class="size-4" /></button>
                @endif
                @if ($bardShows("image") && $bardAssetContainer)
                    <button type="button" x-on:mousedown.prevent x-on:click="insertImage()" class="{{ $bardBtn }}"><flux:icon.photo class="size-4" /></button>
                @endif

                <div class="mx-1 h-5 w-px bg-zinc-200 dark:bg-white/10"></div>

                <button type="button" x-on:mousedown.prevent x-on:click="run('undo')" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10"><flux:icon.arrow-uturn-left class="size-4" /></button>
                <button type="button" x-on:mousedown.prevent x-on:click="run('redo')" class="rounded p-1.5 text-zinc-500 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10"><flux:icon.arrow-uturn-right class="size-4" /></button>
            </div>

            <div
                x-ref="editor"
                x-on:click="onEditorClick($event)"
                class="relative max-w-none px-3 py-2 text-sm text-zinc-800 dark:text-zinc-200 [&_.ProseMirror]:min-h-32 [&_.ProseMirror]:outline-none [&_h1]:mb-1 [&_h1]:mt-3 [&_h1]:text-xl [&_h1]:font-bold [&_h2]:mb-1 [&_h2]:mt-3 [&_h2]:text-lg [&_h2]:font-semibold [&_h3]:mb-1 [&_h3]:mt-3 [&_h3]:text-base [&_h3]:font-semibold [&_h4]:mb-1 [&_h4]:mt-2 [&_h4]:text-base [&_h4]:font-semibold [&_h5]:mb-1 [&_h5]:mt-2 [&_h5]:text-sm [&_h5]:font-semibold [&_h6]:mb-1 [&_h6]:mt-2 [&_h6]:text-sm [&_h6]:font-semibold [&_p]:my-1 [&_ul]:my-1 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:my-1 [&_ol]:list-decimal [&_ol]:pl-5 [&_blockquote]:my-1 [&_blockquote]:border-l-2 [&_blockquote]:border-zinc-300 [&_blockquote]:pl-3 [&_blockquote]:italic dark:[&_blockquote]:border-zinc-600 [&_a]:text-blue-600 [&_a]:underline dark:[&_a]:text-blue-400 [&_img]:my-2 [&_img]:max-w-full [&_img]:max-h-[350px] [&_img]:w-full [&_img]:bg-gray-200 [&_img]:object-contain [&_img]:cursor-pointer [&_img]:rounded-lg [&_img.ProseMirror-selectednode]:ring-2 [&_img.ProseMirror-selectednode]:ring-blue-500 [&_video]:my-2 [&_video]:max-w-full [&_video]:w-full [&_video]:cursor-pointer [&_video]:rounded-lg [&_video.ProseMirror-selectednode]:ring-2 [&_video.ProseMirror-selectednode]:ring-blue-500 [&_audio]:my-2 [&_audio]:w-full [&_audio]:cursor-pointer [&_audio.ProseMirror-selectednode]:ring-2 [&_audio.ProseMirror-selectednode]:ring-blue-500 [&_audio.ProseMirror-selectednode]:rounded-lg [&_.bard-file]:my-2 [&_.bard-file]:inline-flex [&_.bard-file]:cursor-pointer [&_.bard-file]:rounded-lg [&_.bard-file]:border [&_.bard-file]:border-zinc-200 [&_.bard-file]:px-3 [&_.bard-file]:py-1.5 [&_.bard-file]:no-underline dark:[&_.bard-file]:border-white/10 [&_.bard-file.ProseMirror-selectednode]:ring-2 [&_.bard-file.ProseMirror-selectednode]:ring-blue-500"
            >
                <div x-show="imageMenu" x-cloak x-on:click.outside="imageMenu = null" x-bind:style="imageMenu ? `top: ${imageMenu.top}px; left: ${imageMenu.left}px` : ''" class="absolute z-10 flex items-center gap-1 rounded-lg border border-zinc-200 bg-white p-1 text-xs shadow-md dark:border-white/10 dark:bg-zinc-800">
                    @if ($bardAssetContainer)
                        <button type="button" x-on:click="replaceImage()" class="rounded px-2 py-1 font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-white/10">{{ __("Replace") }}</button>
                    @endif
                    <button type="button" x-on:click="deleteImage()" class="rounded px-2 py-1 font-medium text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-500/10">{{ __("Delete") }}</button>
                </div>
            </div>
        </div>
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@elseif ($field["type"] === "link")
    @php
        $isRequired = $field["required"] ?? false;
        $isEntryLink = str_starts_with((string) ($linkValue ?? ""), "entry::");
        $initialMode = $isEntryLink ? "entry" : ($isRequired || filled($linkValue) ? "url" : "none");
    @endphp
    <div x-data="{ mode: '{{ $initialMode }}' }">
        <flux:text class="text-sm font-medium" title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:text>
        @if ($field["instructions"])
            <flux:text class="block text-xs text-zinc-500">{{ $field["instructions"] }}</flux:text>
        @endif

        <div class="mt-1 mb-2 inline-flex overflow-hidden rounded border border-zinc-200 text-xs dark:border-zinc-700">
            @unless ($isRequired)
                <button type="button" x-on:click="mode = 'none'; $wire.set('{{ $prefix }}', '')" x-bind:class="mode === 'none' ? 'bg-zinc-800 text-white dark:bg-zinc-100 dark:text-zinc-900' : ''" class="px-2 py-1">{{ __("None") }}</button>
            @endunless
            <button type="button" x-on:click="mode = 'url'" x-bind:class="mode === 'url' ? 'bg-zinc-800 text-white dark:bg-zinc-100 dark:text-zinc-900' : ''" class="px-2 py-1">{{ __("URL") }}</button>
            <button type="button" x-on:click="mode = 'entry'" x-bind:class="mode === 'entry' ? 'bg-zinc-800 text-white dark:bg-zinc-100 dark:text-zinc-900' : ''" class="px-2 py-1">{{ __("Entry") }}</button>
        </div>

        <div x-show="mode === 'url'">
            @if ($live)
                <flux:input wire:model.live.debounce.600ms="{{ $prefix }}" :placeholder="$field['placeholder'] ?: __('https://...')" size="sm" />
            @else
                <flux:input wire:model="{{ $prefix }}" :placeholder="$field['placeholder'] ?: __('https://...')" size="sm" />
            @endif
        </div>

        <div x-show="mode === 'entry'" x-cloak>
            <flux:select wire:model="{{ $prefix }}" placeholder="{{ __('Select an entry') }}" size="sm">
                @foreach ($this->linkableEntries($field["options"] ?? []) as $linkableEntry)
                    <flux:select.option value="entry::{{ $linkableEntry->id }}">{{ $linkableEntry->collection }}: {{ $linkableEntry->data["title"] ?? ($linkableEntry->slug ?? $linkableEntry->id) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </div>
@elseif ($field["type"] === "replicator")
    @php
        $replicatorKey = implode("-", $path);
        $replicatorMaxItems = $field["max_items"] ?? null;
        $replicatorRowCount = count($rows ?? []);
        $replicatorLimitReached = $replicatorMaxItems !== null && $replicatorRowCount >= $replicatorMaxItems;
        $replicatorConditionTargets = $this->conditionTargetHandles($field["replicator_fields"] ?? []);
    @endphp
    <div>
        <div class="flex items-center justify-between gap-2">
            <div>
                <flux:text class="text-sm font-medium" title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:text>
                @if ($field["instructions"])
                    <flux:text class="block text-xs text-zinc-500">{{ $field["instructions"] }}</flux:text>
                @endif
            </div>
            @if (!empty($rows))
                <div class="flex shrink-0 gap-1">
                    <flux:button size="xs" variant="ghost" icon="chevron-double-down" type="button" title="{{ __('Expand all') }}" x-on:click="$dispatch('replicator-expand-{{ $replicatorKey }}')" />
                    <flux:button size="xs" variant="ghost" icon="chevron-double-up" type="button" title="{{ __('Collapse all') }}" x-on:click="$dispatch('replicator-collapse-{{ $replicatorKey }}')" />
                </div>
            @endif
        </div>

        <div class="mt-2 space-y-3">
            @forelse ($rows ?? [] as $rowIndex => $row)
                <div
                    wire:key="replicator-{{ implode('-', $path) }}-{{ $rowIndex }}"
                    class="rounded-xl bg-gray-200 border border-zinc-200 dark:bg-zinc-900 p-1 dark:border-zinc-700 {{ ($row['_enabled'] ?? true) ? '' : 'opacity-60' }}"
                    x-data="{ collapsed: @js($field['collapsed_by_default'] ?? false) }"
                    x-on:replicator-collapse-{{ $replicatorKey }}.window="collapsed = true"
                    x-on:replicator-expand-{{ $replicatorKey }}.window="collapsed = false"
                >
                    <div class="">
                        <div class="flex items-center justify-between gap-4 py-1 px-3">
                            <button type="button" class="flex min-w-0 hover:text-orange-700 cursor-pointer items-center gap-1 text-sm font-medium text-zinc-600 dark:text-zinc-300" x-on:click="collapsed = !collapsed">
                                <flux:icon.chevron-right x-show="collapsed" class="size-4 shrink-0" />
                                <flux:icon.chevron-down x-show="!collapsed" x-cloak class="size-4 shrink-0" />
                                <span class="truncate">{{ __(":label #:number", ["label" => $field["display"], "number" => $rowIndex + 1]) }}</span>
                            </button>
                            <div class="flex shrink-0 items-center gap-1">
                                <flux:switch wire:model="{{ $prefix }}.{{ $rowIndex }}._enabled" size="sm" title="{{ __('Enabled') }}" />
                                <flux:button size="xs" variant="ghost" icon="document-duplicate" type="button" title="{{ __('Duplicate') }}" :disabled="$replicatorLimitReached" wire:click='duplicateReplicatorRow({{ json_encode($path) }}, {{ $rowIndex }})' />
                                <flux:button size="xs" variant="ghost" icon="trash" type="button" wire:click='removeReplicatorRow({{ json_encode($path) }}, {{ $rowIndex }})' wire:confirm="{{ __('Delete this item? This cannot be undone.') }}" />
                            </div>
                        </div>

                        <div class="mt-1 grid gap-3 rounded-xl bg-white px-2 py-3 dark:bg-zinc-800" x-show="!collapsed" x-cloak>
                            @foreach ($field["replicator_fields"] ?? [] as $sub)
                                @if ($this->fieldIsVisible($sub, $row))
                                    <div>
                                        @include ("collections::livewire.partials.entry-field",
                                            [
                                                "field" => $sub,
                                                "path" => [...$path, $rowIndex, $sub["handle"]],
                                                "rows" => $sub["type"] === "replicator" ? $row[$sub["handle"]] ?? [] : null,
                                                "assetIds" => $sub["type"] === "assets" ? $row[$sub["handle"]] ?? [] : null,
                                                "linkValue" => $sub["type"] === "link" ? $row[$sub["handle"]] ?? "" : null,
                                                "taxonomyIds" => $sub["type"] === "taxonomy" ? $row[$sub["handle"]] ?? [] : null,
                                                "bardValue" => $sub["type"] === "bard" ? $row[$sub["handle"]] ?? "" : null,
                                                "preview" => $preview,
                                                "forceLive" => in_array($sub["handle"], $replicatorConditionTargets, true)
                                            ])
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                </div>
            @empty
                <flux:text class="text-sm text-zinc-500">{{ __("No entries yet.") }}</flux:text>
            @endforelse

            <div class="flex items-center gap-2">
                <flux:button size="sm" variant="filled" type="button" :disabled="$replicatorLimitReached" wire:click='addReplicatorRow({{ json_encode($path) }})'>{{ __("Add :label", ["label" => $field["display"]]) }}</flux:button>

                @if ($replicatorMaxItems !== null)
                    <flux:text class="text-xs text-zinc-500">{{ __(":count / :max", ["count" => $replicatorRowCount, "max" => $replicatorMaxItems]) }}</flux:text>
                @endif
            </div>
        </div>
    </div>
@elseif ($field["type"] === "taxonomy")
    @php
        $taxonomyOptions = $this->taxonomyTerms($field["options"] ?? null);
        $allowsMultiple = ($field["max_items"] ?? null) === null || $field["max_items"] > 1;
        $taxonomyHint = match (true) {
            ($field["min_items"] ?? null) !== null && ($field["max_items"] ?? null) !== null => __(":min–:max selections", ["min" => $field["min_items"], "max" => $field["max_items"]]),
            ($field["min_items"] ?? null) !== null => __("At least :min selections", ["min" => $field["min_items"]]),
            ($field["max_items"] ?? null) !== null => __("Up to :max selections", ["max" => $field["max_items"]]),
            default => null,
        };
    @endphp
    @if ($allowsMultiple)
        <flux:field>
            <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
            <div
                x-data="{
                    open: false,
                    max: {{ $field['max_items'] ?? 'null' }},
                    labels: @js($taxonomyOptions->mapWithKeys(fn ($term) => [(string) $term->id => $term->data['title'] ?? $term->slug])),
                    selected: @js(collect($taxonomyIds ?? [])->map(fn ($id) => (string) $id)->values()),
                    toggle(id) {
                        id = String(id);
                        if (this.selected.includes(id)) {
                            this.selected = this.selected.filter((value) => value !== id);
                        } else {
                            this.selected.push(id);
                            if (this.max && this.selected.length >= this.max) {
                                this.open = false;
                            }
                        }
                    },
                    get label() {
                        return this.selected.length ? this.selected.map((id) => this.labels[id]).join(', ') : '{{ __('Select terms') }}';
                    },
                }"
                class="relative"
                x-on:click.outside="open = false"
            >
                <button type="button" x-on:click="open = !open" class="flex w-full items-center justify-between rounded-lg border border-zinc-200 bg-white px-3 py-2 text-left text-sm shadow-xs dark:border-white/10 dark:bg-white/10 dark:text-zinc-300">
                    <span class="truncate" x-text="label"></span>
                    <flux:icon.chevron-down class="size-4 shrink-0 text-zinc-400" />
                </button>

                <div x-show="open" x-cloak class="absolute z-10 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-zinc-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-zinc-800">
                    @forelse ($taxonomyOptions as $term)
                        <label class="flex cursor-pointer items-center justify-between px-3 py-1.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                            <span>{{ $term->data["title"] ?? $term->slug }}</span>
                            <input type="checkbox" wire:model="{{ $prefix }}" value="{{ $term->id }}" x-on:click="toggle('{{ $term->id }}')" class="peer sr-only" />
                            <flux:icon.check class="hidden size-4 shrink-0 text-zinc-800 peer-checked:block dark:text-zinc-100" />
                        </label>
                    @empty
                        <flux:text class="block px-3 py-1.5 text-sm text-zinc-500">{{ __("No terms yet.") }}</flux:text>
                    @endforelse
                </div>
            </div>
            <flux:description class="mt-0! text-xs">{{ trim(($field["instructions"] ?? "") . " " . ($taxonomyHint ?? "")) }}</flux:description>
            @error ($prefix)
                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
        </flux:field>
    @else
        <flux:field>
            <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
            <flux:select wire:model="{{ $prefix }}.0" placeholder="{{ __('Select a term') }}">
                @foreach ($taxonomyOptions as $term)
                    <flux:select.option value="{{ $term->id }}">{{ $term->data["title"] ?? $term->slug }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:description class="mt-0! text-xs">{{ trim(($field["instructions"] ?? "") . " " . ($taxonomyHint ?? "")) }}</flux:description>
            @error ($prefix)
                <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
            @enderror
        </flux:field>
    @endif
@elseif ($field["type"] === "assets")
    @php
        // Deriving the count/limit check from the resolved, still-existing
        // assets (not the raw stored id array) keeps it in sync with what's
        // actually rendered below — an id whose asset was deleted from the
        // Media Library would otherwise silently occupy a slot forever,
        // hiding the "+" button even though nothing visible is selected.
        $validAssetIds = $this->selectedAssets($assetIds ?? [])
            ->pluck("id")
            ->all();
        $selectedCount = count($validAssetIds);
        $maxReached = ($field["max_items"] ?? null) !== null && $selectedCount >= $field["max_items"];
    @endphp
    <div>
        <flux:text class="text-sm font-medium" title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:text>
        @if ($field["instructions"])
            <flux:text class="block text-xs text-zinc-500">{{ $field["instructions"] }}</flux:text>
        @endif
        @if (($field["min_items"] ?? null) !== null || ($field["max_items"] ?? null) !== null)
            <flux:text class="block text-xs text-zinc-500">
                @if (($field["min_items"] ?? null) !== null && ($field["max_items"] ?? null) !== null)
                    {{ __(":min–:max items", ["min" => $field["min_items"], "max" => $field["max_items"]]) }}
                @elseif (($field["min_items"] ?? null) !== null)
                    {{ __("At least :min items", ["min" => $field["min_items"]]) }}
                @else
                    {{ __("Up to :max items", ["max" => $field["max_items"]]) }}
                @endif
            </flux:text>
        @endif

        <div class="mt-2 flex flex-wrap gap-2">
            @foreach ($this->selectedAssets($assetIds ?? []) as $asset)
                <div wire:key="selected-{{ implode('-', $path) }}-{{ $asset->id }}" class="group relative size-16 overflow-hidden rounded border border-zinc-200 dark:border-zinc-700">
                    @if (str_starts_with((string) $asset->mime_type, "image/"))
                        <img src="{{ $asset->url }}" class="h-full w-full object-cover" alt="{{ $asset->title }}" />
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-zinc-100 text-[10px] uppercase dark:bg-zinc-800">{{ $asset->extension }}</div>
                    @endif
                    <button type="button" wire:click='removeAsset({{ json_encode($path) }}, {{ $asset->id }})' class="absolute right-0 top-0 rounded-bl bg-black/60 px-1 text-xs text-white opacity-0 group-hover:opacity-100">✕</button>
                </div>
            @endforeach

            @unless ($maxReached)
                <button
                    type="button"
                    wire:click='$dispatch("open-asset-picker", { path: {{ json_encode($path) }}, container: {{ json_encode($field['options'] ?? '') }}, selected: {{ json_encode($validAssetIds) }}, max: {{ json_encode($field['max_items'] ?? null) }}, validation: {{ json_encode($field['validation'] ?? null) }} })'
                    class="flex size-16 items-center justify-center rounded border border-dashed border-zinc-300 text-zinc-400 hover:border-zinc-400 hover:text-zinc-600 dark:border-zinc-600"
                >
                    <flux:icon.plus class="size-5" />
                </button>
            @endunless
        </div>

        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </div>
@else
    <flux:field>
        <flux:label title="{{ $field['handle'] }}">{!! $fieldLabel !!}</flux:label>
        @if ($live)
            <flux:input wire:model.live.debounce.600ms="{{ $prefix }}" :placeholder="$field['placeholder']" />
        @else
            <flux:input wire:model="{{ $prefix }}" :placeholder="$field['placeholder']" />
        @endif
        @if ($field["instructions"])
            <flux:description class="mt-0! text-xs">{{ $field["instructions"] }}</flux:description>
        @endif
        @error ($prefix)
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>
@endif
