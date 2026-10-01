<?php

namespace App\Modules\SiteSettings\Support;

use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;

/**
 * The schema.org block editor shared by Site Settings (global blocks) and
 * Collections/EntryForm (per-entry blocks, in a "Schema" tab) — same field
 * pool, same JSON-LD types, just a different owner and a different asset
 * -picker sentinel so a logo pick lands on the right block regardless of
 * which of the two forms opened the picker.
 */
trait HasSchemaBlocks
{
    const SCHEMA_TYPES = ['Organization', 'WebSite', 'ContactPage', 'AboutPage', 'Service', 'CollectionPage'];

    /**
     * One entry per schema.org block to render (see SchemaMarkupBuilder) —
     * more than one is allowed, e.g. Organization + WebSite together. Each
     * block always carries every field key (see defaultSchemaBlock()), even
     * the ones its own type doesn't use, so the form never has to guess a
     * missing array key.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $schemaBlocks = [];

    /**
     * @return array<string, mixed>
     */
    protected function defaultSchemaBlock(string $type = 'Organization'): array
    {
        return [
            'type' => $type,
            'name' => '',
            'alternate_name' => '',
            'description' => '',
            'logo' => '',
            'same_as' => [],
            'email' => '',
            'telephone' => '',
            'contact_type' => '',
            'address_street' => '',
            'address_locality' => '',
            'address_region' => '',
            'address_postal' => '',
            'address_country' => '',
            'service_type' => '',
            'area_served' => '',
        ];
    }

    public function addSchemaBlock(): void
    {
        $this->schemaBlocks[] = $this->defaultSchemaBlock();
    }

    public function removeSchemaBlock(int $index): void
    {
        unset($this->schemaBlocks[$index]);
        $this->schemaBlocks = array_values($this->schemaBlocks);
    }

    #[Computed]
    public function assetContainer(): ?string
    {
        return AssetContainer::orderBy('handle')->value('handle');
    }

    /**
     * The "open-asset-picker" path[0] sentinel used to route a logo pick
     * back to this owner's own schemaAssetsSelected() handler, instead of
     * some other picker sentinel on the same page (e.g. Site Settings'
     * global logo picker vs an entry's own "Schema" tab logo picker).
     */
    protected function schemaLogoSentinel(): string
    {
        return '__schema_logo__';
    }

    public function pickLogo(int $blockIndex): void
    {
        $this->dispatch('open-asset-picker', path: [$this->schemaLogoSentinel(), $blockIndex], container: $this->assetContainer, selected: [], max: 1);
    }

    public function removeLogo(int $blockIndex): void
    {
        $this->schemaBlocks[$blockIndex]['logo'] = '';
    }

    /**
     * Not itself an #[On('assets-selected')] listener — Livewire only keeps
     * the LAST-registered listener per event name across a component (class
     * methods and trait methods alike), so a second one silently replaces
     * the first instead of both firing. A consumer that has its own
     * "assets-selected" listener (like EntryForm, for its "assets"-type
     * fields) must call this from there instead; a consumer with no other
     * listener (like SiteSettingsManager) declares its own thin
     * #[On('assets-selected')] wrapper that just forwards here. Returns
     * whether this was actually a logo pick for this owner, so a caller
     * that goes on to do its own thing knows whether to skip that.
     *
     * @param  array<int, string|int>  $path
     * @param  array<int, int>  $ids
     */
    public function schemaAssetsSelected(array $path, array $ids): bool
    {
        if (($path[0] ?? null) !== $this->schemaLogoSentinel()) {
            return false;
        }

        $this->schemaBlocks[$path[1]]['logo'] = (string) (AssetMeta::whereIn('id', $ids)->first()?->url ?? '');

        return true;
    }

    public function addSameAs(int $blockIndex): void
    {
        $this->schemaBlocks[$blockIndex]['same_as'][] = '';
    }

    public function removeSameAs(int $blockIndex, int $sameAsIndex): void
    {
        unset($this->schemaBlocks[$blockIndex]['same_as'][$sameAsIndex]);
        $this->schemaBlocks[$blockIndex]['same_as'] = array_values($this->schemaBlocks[$blockIndex]['same_as']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function schemaBlockValidationRules(): array
    {
        return [
            'schemaBlocks' => ['array'],
            'schemaBlocks.*.type' => ['required', Rule::in(self::SCHEMA_TYPES)],
            'schemaBlocks.*.name' => ['nullable', 'string', 'max:255'],
            'schemaBlocks.*.alternate_name' => ['nullable', 'string', 'max:255'],
            'schemaBlocks.*.description' => ['nullable', 'string', 'max:1000'],
            'schemaBlocks.*.logo' => ['nullable', 'string', 'max:2048'],
            'schemaBlocks.*.same_as' => ['array'],
            'schemaBlocks.*.same_as.*' => ['nullable', 'string', 'max:2048'],
            'schemaBlocks.*.email' => ['nullable', 'email', 'max:255'],
            'schemaBlocks.*.telephone' => ['nullable', 'string', 'max:50'],
            'schemaBlocks.*.contact_type' => ['nullable', 'string', 'max:255'],
            'schemaBlocks.*.address_street' => ['nullable', 'string', 'max:255'],
            'schemaBlocks.*.address_locality' => ['nullable', 'string', 'max:255'],
            'schemaBlocks.*.address_region' => ['nullable', 'string', 'max:255'],
            'schemaBlocks.*.address_postal' => ['nullable', 'string', 'max:50'],
            'schemaBlocks.*.address_country' => ['nullable', 'string', 'max:255'],
            'schemaBlocks.*.service_type' => ['nullable', 'string', 'max:255'],
            'schemaBlocks.*.area_served' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Drops any blank "sameAs" entries before persisting — mirrors the
     * inline cleanup SiteSettingsManager::save() used to do itself.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    protected function cleanedSchemaBlocks(array $blocks): array
    {
        return array_map(fn (array $block): array => array_merge($block, [
            'same_as' => array_values(array_filter($block['same_as'] ?? [])),
        ]), $blocks);
    }
}
