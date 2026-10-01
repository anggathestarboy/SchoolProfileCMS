<?php

namespace App\Support\Templating;

use App\Models\TaxonomyTerm;
use App\Modules\Collections\Models\Entry;
use App\Modules\Globals\Models\GlobalSetVariable;
use App\Modules\Sites\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Fluent;

/**
 * Backs the @collection/@taxonomy/@sites/@global Blade directives (see
 * TemplatingServiceProvider) — each fetches CMS content the same way
 * PageController does for a single entry, so a template can loop/read it
 * without hand-rolling the Eloquent query itself.
 */
class TemplateTags
{
    /**
     * Entry/TaxonomyTerm columns that live on the table itself rather than
     * inside the "data" json column — used to tell where/sort options like
     * "date" (a column) apart from "featured" (a data field, so it needs
     * the "data->featured" json path) without the caller having to say so.
     */
    protected const COLUMNS = ['id', 'site', 'status', 'slug', 'uri', 'date', 'order', 'collection', 'taxonomy', 'created_at', 'updated_at'];

    public static function global(string $handle): ?Fluent
    {
        $variable = GlobalSetVariable::where('handle', $handle)->where('locale', null)->first();

        return $variable ? FluentWrapper::wrap($variable->data ?? []) : null;
    }

    /**
     * @param  array{site?: string, status?: string, where?: array<string, mixed>, related?: Entry|string, sort?: string|array<int, string>, random?: bool, limit?: int}  $options
     */
    public static function collection(string $handle, array $options = []): Collection
    {
        $query = Entry::where('collection', $handle)->where('status', $options['status'] ?? 'published');

        if ($site = $options['site'] ?? null) {
            $query->where('site', $site);
        }

        static::applyWhere($query, $options['where'] ?? []);
        static::applyRelated($query, $options['related'] ?? null);

        if (($options['random'] ?? false) || ($options['sort'] ?? null)) {
            static::applySort($query, $options);
        } else {
            $query->orderBy('order')->orderByDesc('date');
        }

        return static::applyLimit($query, $options['limit'] ?? null)->get();
    }

    /**
     * @param  array{site?: string, where?: array<string, mixed>, sort?: string|array<int, string>, random?: bool, limit?: int}  $options
     */
    public static function taxonomy(string $handle, array $options = []): Collection
    {
        $query = TaxonomyTerm::where('taxonomy', $handle);

        if ($site = $options['site'] ?? null) {
            $query->where('site', $site);
        }

        static::applyWhere($query, $options['where'] ?? []);
        static::applySort($query, $options);

        return static::applyLimit($query, $options['limit'] ?? null)->get();
    }

    public static function sites(): Collection
    {
        return Site::orderBy('order')->get();
    }

    /**
     * Equality filters — "where" => ["featured" => true] matches entries
     * whose "featured" field is true, whether that's an actual column or
     * (far more often) a key inside the "data" json column.
     *
     * @param  array<string, mixed>  $where
     */
    protected static function applyWhere(Builder $query, array $where): void
    {
        foreach ($where as $field => $value) {
            $query->where(static::column($field), $value);
        }
    }

    /**
     * "sort" => "date:desc" (or "title:asc", or ["date:desc", "title:asc"]
     * for multiple columns) — "random" => true overrides it entirely and
     * ignores any "sort" option.
     *
     * @param  array{sort?: string|array<int, string>, random?: bool}  $options
     */
    protected static function applySort(Builder $query, array $options): void
    {
        if ($options['random'] ?? false) {
            $query->inRandomOrder();

            return;
        }

        foreach ((array) ($options['sort'] ?? []) as $sort) {
            [$field, $direction] = array_pad(explode(':', $sort, 2), 2, 'asc');
            $query->orderBy(static::column($field), $direction);
        }
    }

    /**
     * Entries sharing at least one taxonomy term with $related (an Entry or
     * entry id), excluding $related itself — used for "related posts"
     * blocks. A no-op if $related has no taxonomy fields filled in.
     */
    protected static function applyRelated(Builder $query, Entry|string|null $related): void
    {
        if (! $related) {
            return;
        }

        $related = $related instanceof Entry ? $related : Entry::find($related);

        if (! $related) {
            return;
        }

        $termIds = collect($related->blueprintFields())
            ->where('type', 'taxonomy')
            ->flatMap(fn (array $field): array => (array) ($related->data[$field['handle']] ?? []))
            ->unique()
            ->values();

        if ($termIds->isEmpty()) {
            // Nothing to relate on (no taxonomy field, or none picked) — no
            // entry can be "related", not every entry.
            $query->whereRaw('1 = 0');

            return;
        }

        $taxonomyFieldHandles = collect($related->blueprintFields())->where('type', 'taxonomy')->pluck('handle');

        $query->where('id', '!=', $related->id)->where(function (Builder $query) use ($taxonomyFieldHandles, $termIds): void {
            foreach ($taxonomyFieldHandles as $handle) {
                foreach ($termIds as $termId) {
                    $query->orWhereJsonContains("data->{$handle}", $termId);
                }
            }
        });
    }

    protected static function applyLimit(Builder $query, ?int $limit): Builder
    {
        return $limit ? $query->limit($limit) : $query;
    }

    /**
     * A field is a real table column (e.g. "date", "order") if it's one of
     * COLUMNS; otherwise it's assumed to live inside the "data" json column
     * (e.g. "featured" -> "data->featured"), which is where most
     * blueprint-defined fields actually live.
     */
    protected static function column(string $field): string
    {
        return in_array($field, static::COLUMNS, true) ? $field : "data->{$field}";
    }
}
