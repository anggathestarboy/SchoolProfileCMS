<?php

namespace App\Modules\SiteSettings\Livewire;

use App\Models\Taxonomy;
use App\Modules\Collections\Models\Collection;
use App\Modules\SiteSettings\Models\SiteSetting;
use App\Modules\SiteSettings\Services\LlmsTxtGenerator;
use App\Modules\SiteSettings\Support\HasSchemaBlocks;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Site Settings')]
class SiteSettingsManager extends Component
{
    use HasSchemaBlocks;

    protected function schemaLogoSentinel(): string
    {
        return '__site_settings_logo__';
    }

    #[On('assets-selected')]
    public function assetsSelected(array $path, array $ids): void
    {
        $this->schemaAssetsSelected($path, $ids);
    }

    #[Url('q')]
    public string $activeTab = 'robots';

    public string $robotsTxt = '';

    public string $llmsTxt = '';

    /**
     * @var array<int, string>
     */
    public array $llmsCollections = [];

    /**
     * @var array<int, string>
     */
    public array $llmsTaxonomies = [];

    public int $llmsLimit = 1000;

    public string $llmsAdditionalContent = '';

    public bool $gaEnabled = false;

    public string $gaMeasurementId = '';

    public bool $gscEnabled = false;

    public string $gscVerificationCode = '';

    public function mount(): void
    {
        $settings = SiteSetting::current();

        $this->robotsTxt = (string) $settings->robots_txt;
        $this->llmsTxt = (string) $settings->llms_txt;
        $this->llmsCollections = $settings->llms_config['collections'] ?? [];
        $this->llmsTaxonomies = $settings->llms_config['taxonomies'] ?? [];
        $this->llmsLimit = $settings->llms_config['limit'] ?? 1000;
        $this->llmsAdditionalContent = (string) ($settings->llms_config['additional_content'] ?? '');
        $this->schemaBlocks = array_map(fn (array $block): array => array_merge($this->defaultSchemaBlock(), $block), $settings->schemaBlocks());
        $this->gaEnabled = $settings->googleAnalyticsEnabled();
        $this->gaMeasurementId = (string) $settings->googleAnalyticsMeasurementId();
        $this->gscEnabled = $settings->googleSearchConsoleEnabled();
        $this->gscVerificationCode = (string) $settings->googleSearchConsoleVerificationCode();
    }

    #[Computed]
    public function availableCollections()
    {
        return Collection::orderBy('title')->get();
    }

    #[Computed]
    public function availableTaxonomies()
    {
        return Taxonomy::orderBy('title')->get();
    }

    public function generateLlms(): void
    {
        $this->llmsTxt = app(LlmsTxtGenerator::class)->generate($this->llmsCollections, $this->llmsTaxonomies, $this->llmsLimit, $this->llmsAdditionalContent);
    }

    public function toggleAllCollections(): void
    {
        $allHandles = $this->availableCollections->pluck('handle')->all();

        $this->llmsCollections = count($this->llmsCollections) === count($allHandles) ? [] : $allHandles;
    }

    public function toggleAllTaxonomies(): void
    {
        $allHandles = $this->availableTaxonomies->pluck('handle')->all();

        $this->llmsTaxonomies = count($this->llmsTaxonomies) === count($allHandles) ? [] : $allHandles;
    }

    public function setActiveTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function fillDefaultRobots(): void
    {
        $this->robotsTxt = "User-agent: *\nAllow: /\nSitemap: ".rtrim(config('app.url'), '/').'/sitemap.xml';
    }

    public function fillDefaultLlms(): void
    {
        $this->llmsTxt = '# '.config('app.name')."\n\n> A short summary of what this site is about.\n\n## Pages\n\n- [Home](/): The homepage.\n";
    }

    public function save(): void
    {
        $validated = $this->validate(array_merge([
            'robotsTxt' => ['nullable', 'string'],
            'llmsTxt' => ['nullable', 'string'],
            'llmsCollections' => ['array'],
            'llmsCollections.*' => ['string'],
            'llmsTaxonomies' => ['array'],
            'llmsTaxonomies.*' => ['string'],
            'llmsLimit' => ['required', 'integer', 'min:1'],
            'llmsAdditionalContent' => ['nullable', 'string'],
            'gaEnabled' => ['boolean'],
            'gaMeasurementId' => [Rule::requiredIf($this->gaEnabled), 'nullable', 'string', 'max:255'],
            'gscEnabled' => ['boolean'],
            'gscVerificationCode' => [Rule::requiredIf($this->gscEnabled), 'nullable', 'string', 'max:255'],
        ], $this->schemaBlockValidationRules()));

        $schemaBlocks = $this->cleanedSchemaBlocks($validated['schemaBlocks']);

        SiteSetting::current()->update([
            'robots_txt' => $validated['robotsTxt'] ?: null,
            'llms_txt' => $validated['llmsTxt'] ?: null,
            'llms_config' => [
                'collections' => $validated['llmsCollections'],
                'taxonomies' => $validated['llmsTaxonomies'],
                'limit' => $validated['llmsLimit'],
                'additional_content' => $validated['llmsAdditionalContent'] ?: null,
            ],
            'schema' => $schemaBlocks,
            'integrations' => [
                'ga_enabled' => $validated['gaEnabled'],
                'ga_measurement_id' => $validated['gaMeasurementId'] ?: null,
                'gsc_enabled' => $validated['gscEnabled'],
                'gsc_verification_code' => $validated['gscVerificationCode'] ?: null,
            ],
        ]);

        Flux::toast(variant: 'success', text: __('Site settings saved.'));
    }

    public function render(): View
    {
        return view('site-settings::livewire.site-settings-manager');
    }
}
