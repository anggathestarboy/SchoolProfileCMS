<?php

namespace App\Modules\SiteSettings\Models;

use App\Modules\ActivityLog\Concerns\LogsActivity;
use App\Modules\SiteSettings\Services\SchemaMarkupBuilder;
use Illuminate\Database\Eloquent\Model;

/**
 * A single global row (see current()) holding site-wide, not-per-site
 * configuration: robots.txt, llms.txt, and the schema.org JSON-LD (see
 * SchemaMarkupBuilder) injected into every page's <head>.
 *
 * @property int $id
 * @property string|null $robots_txt
 * @property string|null $llms_txt
 * @property array|null $llms_config
 * @property array|null $schema
 * @property array|null $integrations
 */
class SiteSetting extends Model
{
    use LogsActivity;

    protected $table = 'site_settings';

    protected $fillable = ['robots_txt', 'llms_txt', 'llms_config', 'schema', 'integrations'];

    protected $casts = [
        'llms_config' => 'array',
        'schema' => 'array',
        'integrations' => 'array',
    ];

    /**
     * Whether the Google Analytics snippet should be injected into every
     * page — off by default even when a measurement id is already saved,
     * so a filled-in id doesn't silently start tracking.
     */
    public function googleAnalyticsEnabled(): bool
    {
        return (bool) ($this->integrations['ga_enabled'] ?? false);
    }

    public function googleAnalyticsMeasurementId(): ?string
    {
        return $this->integrations['ga_measurement_id'] ?? null;
    }

    /**
     * Whether the Google Search Console verification meta tag should be
     * injected into every page.
     */
    public function googleSearchConsoleEnabled(): bool
    {
        return (bool) ($this->integrations['gsc_enabled'] ?? false);
    }

    public function googleSearchConsoleVerificationCode(): ?string
    {
        return $this->integrations['gsc_verification_code'] ?? null;
    }

    /**
     * The single settings row, creating it (empty) on first use.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }

    /**
     * This site's schema.org JSON-LD blocks (one per entry in the Schema
     * tab — an admin can add more than one, e.g. Organization + WebSite),
     * skipping any block without a name filled in yet.
     *
     * @return array<int, array<string, mixed>>
     */
    public function structuredDataBlocks(): array
    {
        return app(SchemaMarkupBuilder::class)->buildAll($this->schemaBlocks());
    }

    /**
     * The raw schema blocks as stored, always as a list — transparently
     * upgrading the old single-block format (a flat array with a
     * top-level "type"/"name" key, from before multiple blocks were
     * supported) into a one-item list.
     *
     * @return array<int, array<string, mixed>>
     */
    public function schemaBlocks(): array
    {
        $schema = $this->schema ?? [];

        if (array_is_list($schema)) {
            return $schema;
        }

        return isset($schema['type']) || isset($schema['name']) ? [$schema] : [];
    }

    public function activityLabel(): string
    {
        return 'site settings';
    }
}
