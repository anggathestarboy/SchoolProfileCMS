<?php

namespace App\Modules\SiteSettings\Services;

use App\Models\Taxonomy;
use App\Models\TaxonomyTerm;
use App\Modules\Collections\Models\Collection;
use App\Modules\Collections\Models\Entry;
use App\Modules\Collections\Services\EntryUrlResolver;

/**
 * Builds llms.txt content (per https://llmstxt.org) from the collections'
 * published entries and the taxonomies' terms the user picked in
 * SiteSettingsManager, one "## Section" per collection/taxonomy — this is
 * a one-shot generator (see SiteSettingsManager::generateLlms()), not a
 * live-rendered file: it fills the editable llms.txt textarea, which is
 * what actually gets saved and served.
 */
class LlmsTxtGenerator
{
    /**
     * @param  array<int, string>  $collectionHandles
     * @param  array<int, string>  $taxonomyHandles
     */
    public function generate(array $collectionHandles, array $taxonomyHandles, int $limit, string $additionalContent): string
    {
        $sections = ['# '.config('app.name')];

        foreach (Collection::whereIn('handle', $collectionHandles)->orderBy('handle')->get() as $collection) {
            if ($section = $this->entriesSection($collection, $limit)) {
                $sections[] = $section;
            }
        }

        foreach (Taxonomy::whereIn('handle', $taxonomyHandles)->orderBy('handle')->get() as $taxonomy) {
            if ($section = $this->termsSection($taxonomy, $limit)) {
                $sections[] = $section;
            }
        }

        if (trim($additionalContent) !== '') {
            $sections[] = trim($additionalContent);
        }

        return implode("\n\n", $sections)."\n";
    }

    protected function entriesSection(Collection $collection, int $limit): ?string
    {
        $entries = Entry::where('collection', $collection->handle)->where('status', 'published')->limit($limit)->get();

        if ($entries->isEmpty()) {
            return null;
        }

        $resolver = app(EntryUrlResolver::class);
        $lines = ["## {$collection->title}"];

        foreach ($entries as $entry) {
            $title = $entry->data['title'] ?? $entry->slug ?? $entry->id;
            $url = $resolver->url($collection, $entry);
            $lines[] = $url ? "- [{$title}]({$url})" : "- {$title}";
        }

        return implode("\n", $lines);
    }

    protected function termsSection(Taxonomy $taxonomy, int $limit): ?string
    {
        $terms = TaxonomyTerm::where('taxonomy', $taxonomy->handle)->limit($limit)->get();

        if ($terms->isEmpty()) {
            return null;
        }

        $lines = ["## {$taxonomy->title}"];

        foreach ($terms as $term) {
            $title = $term->data['title'] ?? $term->slug ?? $term->id;
            $lines[] = $term->uri ? "- [{$title}]({$term->uri})" : "- {$title}";
        }

        return implode("\n", $lines);
    }
}
