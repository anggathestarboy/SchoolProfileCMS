<?php

namespace App\Models\Concerns;

/**
 * Mimics Statamic's behavior of storing blueprint field values inside
 * a single JSON `data` column, while still allowing them to be accessed
 * like normal attributes (e.g. $entry->title, $entry->seo_description).
 *
 * Real Eloquent columns / relationships always take priority over
 * values stored inside the `data` json blob.
 */
trait HasFlexibleData
{
    /**
     * The json column that stores dynamic blueprint field values.
     * Override in the model if the column name differs (e.g. "meta").
     */
    protected string $dataColumn = 'data';

    public function getAttribute($key)
    {
        $value = parent::getAttribute($key);

        if ($value !== null || $this->hasAttribute($key) || $this->relationLoaded($key)) {
            return $value;
        }

        return data_get($this->{$this->dataColumn}, $key);
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function hasAttribute($key): bool
    {
        return array_key_exists($key, $this->attributes)
            || $this->hasGetMutator($key)
            || $this->hasAttributeMutator($key);
    }

    /**
     * Convenience accessor, closer to Statamic's $entry->get('field', $default)
     */
    public function get(string $key, $default = null)
    {
        return data_get($this->{$this->dataColumn}, $key, $default);
    }

    public function set(string $key, $value): static
    {
        $data = $this->{$this->dataColumn} ?? [];
        data_set($data, $key, $value);
        $this->{$this->dataColumn} = $data;

        return $this;
    }
}
