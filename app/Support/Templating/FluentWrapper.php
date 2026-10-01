<?php

namespace App\Support\Templating;

use Illuminate\Support\Fluent;

/**
 * Recursively wraps an associative array (including nested replicator-style
 * rows) into Fluent instances, so template code can read a field as
 * $data->title as well as $data['title'], at any nesting depth. Shared by
 * Entry::fluentData(), TaxonomyTerm::fluentData(), and TemplateTags::global()
 * so all four @collection/@taxonomy/@sites/@global directives expose data
 * the same way.
 */
class FluentWrapper
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function wrap(array $data): Fluent
    {
        return new Fluent(collect($data)->map(function ($value) {
            if (! is_array($value)) {
                return $value;
            }

            return array_is_list($value)
                ? collect($value)->map(fn ($item) => is_array($item) ? static::wrap($item) : $item)->all()
                : static::wrap($value);
        })->all());
    }
}
