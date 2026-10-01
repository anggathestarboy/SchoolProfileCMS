<?php

namespace App\Modules\Blueprints\Support;

/**
 * Evaluates a blueprint field's "show when"/"hide when" condition (see
 * HasFieldBuilder's $conditionModes/$conditionOperators, and
 * normalizeFieldForStorage()'s "conditions" key) against the current value
 * of the field it depends on. Shared by every module that fills in blueprint
 * field values (Collections, Globals, Taxonomies, Navigation, Forms) so a
 * field's visibility and validation agree with what the blueprint editor
 * configured.
 *
 * $scopeValues is whatever handle => value bag the condition's target field
 * lives in: the entry's own flat data for a top-level field, or the current
 * replicator row's data for one of its sub-fields — conditions can only
 * target a field within their own scope (see HasFieldBuilder::conditionErrors()).
 */
trait EvaluatesFieldConditions
{
    /**
     * @param  array<string, mixed>  $field
     * @param  array<string, mixed>  $scopeValues
     */
    protected function fieldIsVisible(array $field, array $scopeValues): bool
    {
        $conditions = $field['conditions'] ?? null;
        $mode = $conditions['mode'] ?? 'always';

        if (! $conditions || $mode === 'always') {
            return true;
        }

        $matches = $this->fieldConditionMatches($conditions, $scopeValues[$conditions['field']] ?? null);

        return $mode === 'show' ? $matches : ! $matches;
    }

    /**
     * @param  array{operator?: string, value?: string}  $conditions
     */
    protected function fieldConditionMatches(array $conditions, mixed $actual): bool
    {
        $expected = $conditions['value'] ?? '';

        return match ($conditions['operator'] ?? 'equals') {
            'not' => ! $this->conditionValueEquals($actual, $expected),
            'strict' => $actual === $expected,
            'contains' => is_array($actual)
                ? in_array($expected, $actual, false)
                : str_contains((string) $actual, (string) $expected),
            'contains_any' => is_array($actual) && array_intersect(
                $actual,
                array_filter(array_map('trim', explode(',', (string) $expected)))
            ) !== [],
            default => $this->conditionValueEquals($actual, $expected),
        };
    }

    protected function conditionValueEquals(mixed $actual, string $expected): bool
    {
        if (is_bool($actual)) {
            return $actual === filter_var($expected, FILTER_VALIDATE_BOOLEAN);
        }

        if (is_array($actual)) {
            return in_array($expected, $actual, false);
        }

        return (string) $actual === $expected;
    }

    /**
     * Handles that at least one field in $fields (its own sibling scope —
     * see the class docblock) depends on via a "show when"/"hide when"
     * condition. The content-filling views bind these specific fields'
     * inputs with wire:model.live instead of the usual deferred wire:model,
     * so a dependent field's visibility updates immediately as the editor
     * types/toggles/selects, the same way it already does wherever Live
     * Preview happens to be on — instead of only catching up on whatever
     * later request happens to sync it.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<int, string>
     */
    protected function conditionTargetHandles(array $fields): array
    {
        return collect($fields)
            ->pluck('conditions.field')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
