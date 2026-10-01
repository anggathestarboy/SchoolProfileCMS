<section class="w-full max-w-7xl space-y-6">
    <div class="flex items-center justify-start gap-2 border py-2 px-4 rounded-xl dark:border-zinc-500">
        <svg class="w-6 h-6 text-gray-800 dark:text-white" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12a1 1 0 0 0-1 1v4a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-4a1 1 0 0 0-1-1M5 12h14M5 12a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h14a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1m-2 3h.01M14 15h.01M17 9h.01M14 9h.01" />
        </svg>

        <flux:heading size="xl">{{ __("Site Settings") }}</flux:heading>
    </div>

    <div class="flex gap-1 border-b border-zinc-200 dark:border-zinc-700">
        <button type="button" wire:click="setActiveTab('robots')" class="border-b-2 px-3 py-2 text-sm cursor-pointer {{ $activeTab === 'robots' ? 'border-zinc-800 font-medium text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">{{ __("robots.txt") }}</button>
        <button type="button" wire:click="setActiveTab('llms')" class="border-b-2 px-3 py-2 text-sm cursor-pointer {{ $activeTab === 'llms' ? 'border-zinc-800 font-medium text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">{{ __("llms.txt") }}</button>
        <button type="button" wire:click="setActiveTab('schema')" class="border-b-2 px-3 py-2 text-sm cursor-pointer {{ $activeTab === 'schema' ? 'border-zinc-800 font-medium text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">{{ __("Schema") }}</button>
        <button type="button" wire:click="setActiveTab('integrations')" class="border-b-2 px-3 py-2 text-sm cursor-pointer {{ $activeTab === 'integrations' ? 'border-zinc-800 font-medium text-zinc-900 dark:border-zinc-100 dark:text-zinc-100' : 'border-transparent text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300' }}">{{ __("Integrations") }}</button>
    </div>

    <form wire:submit="save" class="space-y-6">
        @if ($activeTab === "robots")
            <flux:field>
                <div class="mb-1 flex items-center justify-between">
                    <flux:label>{{ __("robots.txt") }}</flux:label>
                    <flux:button size="xs" type="button" wire:click="fillDefaultRobots">{{ __("Fill default") }}</flux:button>
                </div>
                <flux:textarea wire:model="robotsTxt" rows="14" class="font-mono text-xs" placeholder="User-agent: *&#10;Allow: /" />
                <flux:description>{{ __("Served as-is at /robots.txt.") }}</flux:description>
            </flux:field>
        @elseif ($activeTab === "llms")
            <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                <flux:field>
                    <div class="mb-1 flex items-center justify-between">
                        <flux:label>{{ __("Select Collections") }}</flux:label>
                        <flux:button size="xs" type="button" wire:click="toggleAllCollections">{{ __("Select / Deselect all") }}</flux:button>
                    </div>
                    <flux:checkbox.group wire:model="llmsCollections">
                        @forelse ($this->availableCollections as $collection)
                            <flux:checkbox value="{{ $collection->handle }}" label="{{ $collection->title }}" />
                        @empty
                            <flux:text class="text-zinc-500">{{ __("No collections yet.") }}</flux:text>
                        @endforelse
                    </flux:checkbox.group>
                    <flux:description>{{ __("Published entries from these collections will be listed in llms.txt.") }}</flux:description>
                </flux:field>

                <flux:field>
                    <div class="mb-1 flex items-center justify-between">
                        <flux:label>{{ __("Select Taxonomies") }}</flux:label>
                        <flux:button size="xs" type="button" wire:click="toggleAllTaxonomies">{{ __("Select / Deselect all") }}</flux:button>
                    </div>
                    <flux:checkbox.group wire:model="llmsTaxonomies">
                        @forelse ($this->availableTaxonomies as $taxonomy)
                            <flux:checkbox value="{{ $taxonomy->handle }}" label="{{ $taxonomy->title }}" />
                        @empty
                            <flux:text class="text-zinc-500">{{ __("No taxonomies yet.") }}</flux:text>
                        @endforelse
                    </flux:checkbox.group>
                    <flux:description>{{ __("Terms from these taxonomies will be listed in llms.txt.") }}</flux:description>
                </flux:field>

                <flux:input type="number" min="1" wire:model="llmsLimit" :label="__('Entries/terms limit')" :description="__('Maximum number of links to include for each collection/taxonomy.')" />

                <flux:textarea wire:model="llmsAdditionalContent" :label="__('Additional content')" :description="__('Extra text appended after the generated lists.')" rows="4" />

                <flux:button size="sm" type="button" variant="primary" wire:click="generateLlms">{{ __("Generate") }}</flux:button>
            </div>

            <flux:field>
                <div class="mb-1 flex items-center justify-between">
                    <flux:label>{{ __("llms.txt") }}</flux:label>
                    <flux:button size="xs" type="button" wire:click="fillDefaultLlms">{{ __("Fill blank default") }}</flux:button>
                </div>
                <flux:textarea wire:model="llmsTxt" rows="14" class="font-mono text-xs" placeholder="# Site name&#10;&#10;> Summary" />
                <flux:description>{{ __("This is what actually gets saved and served at /llms.txt — Generate fills it in, but you can still edit it by hand before saving.") }}</flux:description>
            </flux:field>
        @elseif ($activeTab === "schema")
            <flux:text class="text-sm text-zinc-500">{{ __('Add as many schema.org blocks as you need — e.g. Organization and WebSite together. Types that need a specific page (Article, BlogPosting, NewsArticle) aren\'t offered here, since this schema is the same on every page.') }}</flux:text>

            <div class="space-y-4">
                @forelse ($schemaBlocks as $index => $block)
                    @include ("site-settings::livewire.partials.schema-block", ["index" => $index, "block" => $block])
                @empty
                    <flux:text class="text-zinc-500">{{ __("No schema blocks yet.") }}</flux:text>
                @endforelse
            </div>

            <flux:button size="sm" type="button" wire:click="addSchemaBlock">{{ __("Add schema") }}</flux:button>
        @else
            <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                <flux:switch wire:model.live="gaEnabled" label="{{ __('Google Analytics') }}" description="{{ __('Injects the GA4 tracking snippet into every page when enabled.') }}" />
                @if ($gaEnabled)
                    <flux:input wire:model="gaMeasurementId" :label="__('Measurement ID')" placeholder="G-XXXXXXXXXX" />
                @endif
            </div>

            <div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
                <flux:switch wire:model.live="gscEnabled" label="{{ __('Google Search Console') }}" description="{{ __('Injects the site-verification meta tag into every page when enabled.') }}" />
                @if ($gscEnabled)
                    <flux:input wire:model="gscVerificationCode" :label="__('Verification code')" :description="__('The content value from the meta tag Search Console gives you, not the full <meta> tag.')" placeholder="abcdefg1234567" />
                @endif
            </div>
        @endif

        <div class="flex justify-end">
            <flux:button variant="primary" type="submit">{{ __("Save") }}</flux:button>
        </div>
    </form>

    @livewire ("media::asset-picker")
</section>
