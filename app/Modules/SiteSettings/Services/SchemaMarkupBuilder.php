<?php

namespace App\Modules\SiteSettings\Services;

/**
 * Builds the site-wide schema.org JSON-LD blocks from SiteSetting::$schema
 * (a list of blocks — see SiteSettingsManager, which lets an admin add more
 * than one, e.g. Organization + WebSite together) — every type shares one
 * flat pool of fields (name, description, address, etc.), each type just
 * reads the ones relevant to its own JSON-LD shape. Types that only make
 * sense per-page (Article, BlogPosting, NewsArticle — they need a specific
 * entry's title/date/author) are intentionally not offered here; this only
 * covers types with a sensible single, site-wide value.
 */
class SchemaMarkupBuilder
{
    /**
     * @param  array<int, array<string, mixed>>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function buildAll(array $blocks): array
    {
        return collect($blocks)
            ->map(fn (array $block): ?array => $this->build($block))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    public function build(array $schema): ?array
    {
        $name = trim((string) ($schema['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $data = match ($schema['type'] ?? 'Organization') {
            'WebSite' => $this->website($schema, $name),
            'ContactPage' => $this->page('ContactPage', $schema, $name),
            'AboutPage' => $this->page('AboutPage', $schema, $name),
            'Service' => $this->service($schema, $name),
            'CollectionPage' => $this->page('CollectionPage', $schema, $name),
            default => $this->organization($schema, $name),
        };

        return array_merge(['@context' => 'https://schema.org'], $data);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function organization(array $schema, string $name): array
    {
        $contactPoint = array_filter([
            '@type' => 'ContactPoint',
            'telephone' => $schema['telephone'] ?? null,
            'contactType' => $schema['contact_type'] ?? null,
            'email' => $schema['email'] ?? null,
        ]);

        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $schema['address_street'] ?? null,
            'addressLocality' => $schema['address_locality'] ?? null,
            'addressRegion' => $schema['address_region'] ?? null,
            'postalCode' => $schema['address_postal'] ?? null,
            'addressCountry' => $schema['address_country'] ?? null,
        ]);

        return array_filter([
            '@type' => 'Organization',
            'name' => $name,
            'alternateName' => $schema['alternate_name'] ?? null,
            'description' => $schema['description'] ?? null,
            'url' => config('app.url'),
            'logo' => $schema['logo'] ?? null,
            'email' => $schema['email'] ?? null,
            'sameAs' => array_values(array_filter($schema['same_as'] ?? [])) ?: null,
            'contactPoint' => count($contactPoint) > 1 ? [$contactPoint] : null,
            'address' => count($address) > 1 ? $address : null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function website(array $schema, string $name): array
    {
        return array_filter([
            '@type' => 'WebSite',
            'name' => $name,
            'description' => $schema['description'] ?? null,
            'url' => config('app.url'),
            'inLanguage' => app()->getLocale(),
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function page(string $type, array $schema, string $name): array
    {
        return array_filter([
            '@type' => $type,
            'name' => $name,
            'description' => $schema['description'] ?? null,
            'url' => config('app.url'),
            'inLanguage' => app()->getLocale(),
            'primaryImageOfPage' => $schema['logo'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     */
    protected function service(array $schema, string $name): array
    {
        $provider = array_filter([
            '@type' => 'Organization',
            'name' => $name,
            'url' => config('app.url'),
            'description' => $schema['description'] ?? null,
            'sameAs' => array_values(array_filter($schema['same_as'] ?? [])) ?: null,
        ], fn ($value) => $value !== null);

        $areaServed = filled($schema['area_served'] ?? null)
            ? ['@type' => 'AdministrativeArea', 'name' => $schema['area_served']]
            : null;

        return array_filter([
            '@type' => 'Service',
            'name' => $name,
            'description' => $schema['description'] ?? null,
            'serviceType' => $schema['service_type'] ?? null,
            'areaServed' => $areaServed,
            'provider' => $provider,
        ], fn ($value) => $value !== null);
    }
}
