@php
    $isOriginLocale = $this->isOriginLocale();
    $robotsOptions = \App\Modules\Collections\Livewire\EntryForm::SEO_ROBOTS_OPTIONS;
    $readOnlyBox = fn ($value) => is_scalar($value) ? ($value ?: "—") : __("Set in the default language");
    // Google typically truncates a title/description shown in search
    // results past these lengths — enforced as a hard cap (maxlength below,
    // matching EntryForm::seoValidationRules()) so a value never gets saved
    // already truncated by Google on the results page.
    $titleLimit = 60;
    $descriptionLimit = 160;
@endphp

<div class="space-y-6">
    <flux:field x-data="{ len: {{ strlen((string) ($seo['meta_title'] ?? '')) }} }">
        <flux:label>{{ __("Meta Title") }} <span class="text-red-500">*</span></flux:label>
        <flux:input wire:model="seo.meta_title" maxlength="{{ $titleLimit }}" x-on:input="len = $event.target.value.length" />
        <div class="flex justify-end">
            <flux:text class="text-xs" x-text="len + ' / {{ $titleLimit }}'" x-bind:class="len > {{ $titleLimit }} ? 'text-red-500' : 'text-zinc-400'"></flux:text>
        </div>
        @error ("seo.meta_title")
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>

    <flux:field x-data="{ len: {{ strlen((string) ($seo['meta_description'] ?? '')) }} }">
        <flux:label>{{ __("Meta Description") }} <span class="text-red-500">*</span></flux:label>
        <flux:textarea wire:model="seo.meta_description" maxlength="{{ $descriptionLimit }}" x-on:input="len = $event.target.value.length" rows="3" />
        <div class="flex justify-end">
            <flux:text class="text-xs" x-text="len + ' / {{ $descriptionLimit }}'" x-bind:class="len > {{ $descriptionLimit }} ? 'text-red-500' : 'text-zinc-400'"></flux:text>
        </div>
        @error ("seo.meta_description")
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>

    <flux:field>
        <flux:label>{{ __("Meta Canonical") }}</flux:label>
        <flux:input wire:model="seo.meta_canonical" placeholder="https://example.com/page" />
        <flux:description>{{ __("Meta canonical can be automatic if it is empty.") }}</flux:description>
        @error ("seo.meta_canonical")
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>

    <flux:field>
        <flux:label>{{ __("Robots") }} <span class="text-red-500">*</span></flux:label>
        <flux:checkbox.group wire:model="seo.robots">
            @foreach ($robotsOptions as $value => $label)
                <flux:checkbox value="{{ $value }}" label="{{ $label }}" />
            @endforeach
        </flux:checkbox.group>
        <flux:description>{{ __('If left blank then the default is "noindex, nofollow". Checking "No Index" also excludes this entry from sitemap.xml.') }}</flux:description>
        @error ("seo.robots")
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>

    <flux:field>
        <flux:label>{{ __("Meta Featured Image") }} <span class="text-red-500">*</span></flux:label>
        @if (! $isOriginLocale)
            <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">{{ __("Set in the default language") }}</div>
            <flux:description>{{ __("Same for every language — edit it from the default language.") }}</flux:description>
        @else
            <div class="mt-2 flex flex-wrap gap-2">
                @foreach ($this->selectedAssets($seo["meta_featured_image"] ?? []) as $asset)
                    <div class="group relative size-16 overflow-hidden rounded border border-zinc-200 dark:border-zinc-700">
                        @if (str_starts_with((string) $asset->mime_type, "image/"))
                            <img src="{{ $asset->url }}" class="h-full w-full object-cover" alt="{{ $asset->title }}" />
                        @else
                            <div class="flex h-full w-full items-center justify-center bg-zinc-100 text-[10px] uppercase dark:bg-zinc-800">{{ $asset->extension }}</div>
                        @endif
                        <button type="button" wire:click="removeSeoImage({{ $asset->id }})" class="absolute right-0 top-0 rounded-bl bg-black/60 px-1 text-xs text-white opacity-0 group-hover:opacity-100">✕</button>
                    </div>
                @endforeach

                @if (empty($seo["meta_featured_image"] ?? []))
                    <button type="button" wire:click="pickSeoImage" :disabled="! $this->assetContainer" class="flex size-16 items-center justify-center rounded border border-dashed border-zinc-300 text-zinc-400 hover:border-zinc-400 hover:text-zinc-600 dark:border-zinc-600">
                        <flux:icon.plus class="size-5" />
                    </button>
                @endif
            </div>
        @endif
        @error ("seo.meta_featured_image")
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>

    <flux:field>
        <flux:label>{{ __("Meta Author") }} <span class="text-red-500">*</span></flux:label>
        @if (! $isOriginLocale)
            <div class="rounded-md border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-500 dark:border-zinc-700 dark:bg-zinc-800">{{ __("Set in the default language") }}</div>
            <flux:description>{{ __("Same for every language — edit it from the default language.") }}</flux:description>
        @else
            <flux:select wire:model="seo.meta_author" placeholder="{{ __('Select an author') }}">
                @foreach ($this->availableAuthors() as $author)
                    <flux:select.option value="{{ $author->id }}">{{ $author->name }}</flux:select.option>
                @endforeach
            </flux:select>
        @endif
        @error ("seo.meta_author")
            <flux:text class="text-red-600 dark:text-red-400">{{ $message }}</flux:text>
        @enderror
    </flux:field>

    <div class="grid grid-cols-2 gap-4">
        <flux:field>
            <flux:label>{{ __("Sitemap Priority") }}</flux:label>
            <flux:select wire:model="seo.sitemap_priority">
                @foreach (\App\Modules\Collections\Livewire\EntryForm::SEO_SITEMAP_PRIORITIES as $priority)
                    <flux:select.option value="{{ $priority }}">{{ $priority }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>

        <flux:field>
            <flux:label>{{ __("Sitemap Changefreq") }}</flux:label>
            <flux:select wire:model="seo.sitemap_changefreq">
                @foreach (\App\Modules\Collections\Livewire\EntryForm::SEO_SITEMAP_CHANGEFREQS as $changefreq)
                    <flux:select.option value="{{ $changefreq }}">{{ ucfirst($changefreq) }}</flux:select.option>
                @endforeach
            </flux:select>
        </flux:field>
    </div>
</div>
