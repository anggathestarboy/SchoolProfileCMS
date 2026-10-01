@php $prefix = "schemaBlocks.{$index}"; @endphp

<div class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-white/10">
    <div class="flex items-center justify-between">
        <flux:select wire:model.live="{{ $prefix }}.type" :label="__('Schema type')" class="max-w-xs">
            <flux:select.option value="Organization">{{ __('Organization') }}</flux:select.option>
            <flux:select.option value="WebSite">{{ __('WebSite') }}</flux:select.option>
            <flux:select.option value="ContactPage">{{ __('ContactPage') }}</flux:select.option>
            <flux:select.option value="AboutPage">{{ __('AboutPage') }}</flux:select.option>
            <flux:select.option value="Service">{{ __('Service') }}</flux:select.option>
            <flux:select.option value="CollectionPage">{{ __('CollectionPage') }}</flux:select.option>
        </flux:select>

        <flux:button size="sm" variant="danger" type="button" wire:click="removeSchemaBlock({{ $index }})">{{ __('Remove') }}</flux:button>
    </div>

    <flux:input wire:model="{{ $prefix }}.name" :label="__('Name')" placeholder="Acme Inc." />

    @if ($block['type'] === 'Organization')
        <flux:input wire:model="{{ $prefix }}.alternate_name" :label="__('Alternate name')" placeholder="Acme" />
    @endif

    <flux:textarea wire:model="{{ $prefix }}.description" :label="__('Description')" rows="3" />

    @if (in_array($block['type'], ['Organization', 'ContactPage', 'AboutPage']))
        <flux:field>
            <flux:label>{{ __('Logo / image') }}</flux:label>
            <div class="flex items-center gap-3">
                @if ($block['logo'])
                    <img src="{{ $block['logo'] }}" class="size-16 rounded border border-zinc-200 object-contain dark:border-white/10" alt="" />
                    <flux:button size="sm" variant="danger" type="button" wire:click="removeLogo({{ $index }})">{{ __('Remove') }}</flux:button>
                @else
                    <flux:button size="sm" type="button" wire:click="pickLogo({{ $index }})" :disabled="! $this->assetContainer">{{ __('Choose logo') }}</flux:button>
                @endif
            </div>
        </flux:field>
    @endif

    @if ($block['type'] === 'Organization')
        <flux:field>
            <flux:label>{{ __('Social profiles (sameAs)') }}</flux:label>
            <div class="space-y-2">
                @foreach ($block['same_as'] as $sameAsIndex => $url)
                    <div wire:key="same-as-{{ $index }}-{{ $sameAsIndex }}" class="flex gap-2">
                        <flux:input wire:model="{{ $prefix }}.same_as.{{ $sameAsIndex }}" placeholder="https://twitter.com/acme" class="flex-1" />
                        <flux:button size="sm" variant="ghost" icon="trash" type="button" wire:click="removeSameAs({{ $index }}, {{ $sameAsIndex }})" />
                    </div>
                @endforeach
            </div>
            <flux:button size="sm" type="button" wire:click="addSameAs({{ $index }})" class="mt-2">{{ __('Add profile') }}</flux:button>
        </flux:field>

        <div class="grid grid-cols-2 gap-4">
            <flux:input wire:model="{{ $prefix }}.email" type="email" :label="__('Email')" placeholder="hello@acme.com" />
            <flux:input wire:model="{{ $prefix }}.telephone" :label="__('Telephone')" placeholder="+62 812 3456 7890" />
        </div>
        <flux:input wire:model="{{ $prefix }}.contact_type" :label="__('Contact type')" placeholder="customer service" />

        <div class="space-y-4 border-t border-zinc-200 pt-4 dark:border-white/10">
            <flux:subheading>{{ __('Address') }}</flux:subheading>
            <flux:input wire:model="{{ $prefix }}.address_street" :label="__('Street address')" />
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="{{ $prefix }}.address_locality" :label="__('City')" />
                <flux:input wire:model="{{ $prefix }}.address_region" :label="__('Region/Province')" />
            </div>
            <div class="grid grid-cols-2 gap-4">
                <flux:input wire:model="{{ $prefix }}.address_postal" :label="__('Postal code')" />
                <flux:input wire:model="{{ $prefix }}.address_country" :label="__('Country')" placeholder="ID" />
            </div>
        </div>
    @endif

    @if ($block['type'] === 'Service')
        <flux:input wire:model="{{ $prefix }}.service_type" :label="__('Service type')" placeholder="Web Design" />
        <flux:input wire:model="{{ $prefix }}.area_served" :label="__('Area served')" placeholder="Indonesia" />
    @endif
</div>
