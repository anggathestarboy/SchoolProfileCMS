<?php

namespace App\Modules\Blueprints\Support;

use Illuminate\Support\Facades\Validator as ValidatorFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * The Statamic-style field builder shared by Blueprints and Fieldsets: a
 * field tree (top-level fields, each optionally containing
 * "replicator_fields" of the same shape to any depth), with add/remove/
 * reorder, per-type validation, and normalization for storage. The
 * consuming component must expose #[Computed] availableAssetContainers(),
 * availableCollections(), availableFieldsets() and availableTaxonomies()
 * (used by the "assets"/"link"/"fieldset"/"taxonomy" field types).
 */
trait HasFieldBuilder
{
    /**
     * @var array<string, string>
     */
    protected static array $fieldtypes = [
        'text' => 'Text',
        'textarea' => 'Textarea',
        'markdown' => 'Markdown',
        'bard' => 'Bard (rich text)',
        'number' => 'Number',
        'toggle' => 'Toggle',
        'date' => 'Date',
        'select' => 'Select',
        'radio' => 'Radio',
        'checkbox' => 'Checkbox',
        'replicator' => 'Replicator',
        'assets' => 'Assets',
        'link' => 'Link',
        'fieldset' => 'Fieldset',
        'taxonomy' => 'Taxonomy',
        'author' => 'Author',
    ];

    /**
     * @var array<int, string>
     */
    protected static array $optionBasedTypes = ['select', 'radio', 'checkbox'];

    /**
     * @var array<string, string>
     */
    protected static array $conditionOperators = [
        'equals' => 'equals',
        'not' => 'not',
        'contains' => 'contains',
        'contains_any' => 'contains any',
        'strict' => '===',
    ];

    protected static array $conditionModes = ['always', 'show', 'hide'];

    /**
     * Every toolbar button a "bard" field can offer — a field's own
     * "toolbar" setting is a subset of these keys (empty means "show all").
     * Mirrors Statamic's Bard fieldtype's configurable toolbar.
     *
     * @var array<string, string>
     */
    protected static array $bardToolbarButtons = [
        'bold' => 'Bold',
        'italic' => 'Italic',
        'underline' => 'Underline',
        'strike' => 'Strikethrough',
        'h1' => 'Heading 1',
        'h2' => 'Heading 2',
        'h3' => 'Heading 3',
        'h4' => 'Heading 4',
        'h5' => 'Heading 5',
        'h6' => 'Heading 6',
        'bulletlist' => 'Bulleted list',
        'orderedlist' => 'Numbered list',
        'blockquote' => 'Blockquote',
        'link' => 'Link',
        'image' => 'Image',
    ];

    /**
     * A field tree: each field may itself contain a "replicator_fields"
     * array of the same shape, recursively, to any depth.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $fields = [];

    protected function mapFieldFromStorage(array $field): array
    {
        return [
            '_uid' => (string) Str::uuid(),
            'handle' => $field['handle'] ?? '',
            'display' => $field['display'] ?? '',
            'type' => $field['type'] ?? 'text',
            'required' => (bool) ($field['required'] ?? false),
            'localizable' => (bool) ($field['localizable'] ?? false),
            'options' => is_array($field['options'] ?? null) ? implode(', ', $field['options']) : (string) ($field['options'] ?? ''),
            'placeholder' => $field['placeholder'] ?? '',
            'default' => ($field['type'] ?? 'text') === 'toggle'
                ? $this->toggleDefaultToString($field['default'] ?? null)
                : (is_array($field['default'] ?? null) ? implode(', ', $field['default']) : (string) ($field['default'] ?? '')),
            'validation' => $field['validation'] ?? '',
            'instructions' => $field['instructions'] ?? '',
            'sometimes' => (bool) ($field['validate_sometimes'] ?? false),
            'condition_mode' => $field['conditions']['mode'] ?? 'always',
            'condition_field' => $field['conditions']['field'] ?? '',
            'condition_operator' => $field['conditions']['operator'] ?? 'equals',
            'condition_value' => $field['conditions']['value'] ?? '',
            'min_items' => isset($field['min_items']) ? (string) $field['min_items'] : '',
            'max_items' => isset($field['max_items']) ? (string) $field['max_items'] : '',
            'collapsed_by_default' => (bool) ($field['collapsed_by_default'] ?? false),
            'toolbar' => array_values($field['toolbar'] ?? []),
            'tab' => $field['tab'] ?? 'main',
            'section' => $field['section'] ?? '',
            'replicator_fields' => collect($field['replicator_fields'] ?? [])
                ->map(fn (array $sub): array => $this->mapFieldFromStorage($sub))
                ->all(),
        ];
    }

    /**
     * The editor's toggle default is a "true"/"false" radio group, but
     * storage holds a real boolean (see normalizeFieldForStorage()) — except
     * for blueprints saved before that was the case, which still have the
     * legacy "true"/"false" string. Handles both so old data keeps editing
     * correctly until the blueprint is next saved.
     */
    protected function toggleDefaultToString(mixed $default): string
    {
        $value = is_string($default) ? $default === 'true' : (bool) $default;

        return $value ? 'true' : 'false';
    }

    public function addField(string $tab = 'main', string $section = ''): void
    {
        $this->fields[] = array_merge($this->fieldDefaults(), ['tab' => $tab, 'section' => $section, '_uid' => (string) Str::uuid()]);
    }

    /**
     * Add a sub-field to the replicator located at $path (a list of
     * indexes, e.g. [0] for the top-level field, [0, 2] for the 3rd
     * sub-field's own replicator, and so on to any depth).
     */
    public function addReplicatorField(array $path): void
    {
        $field = &$this->fieldAt($path);
        $field['replicator_fields'][] = array_merge($this->fieldDefaults(), ['_uid' => (string) Str::uuid()]);
    }

    public function removeField(array $path): void
    {
        $index = array_pop($path);
        $siblings = &$this->siblingsAt($path);
        unset($siblings[$index]);
        $siblings = array_values($siblings);
    }

    /**
     * Insert a copy of the field at $path right after itself, including any
     * nested replicator fields. The copy's handle gets a "_copy" suffix (and
     * every field, at any depth, a fresh "_uid") so it doesn't collide with
     * the original until renamed.
     */
    public function duplicateField(array $path): void
    {
        $field = $this->fieldAt($path);

        $duplicate = $field;
        $duplicate['_uid'] = (string) Str::uuid();
        $duplicate['handle'] = $field['handle'] !== '' ? "{$field['handle']}_copy" : '';
        $duplicate['replicator_fields'] = $this->duplicateFieldsTree($field['replicator_fields'] ?? []);

        $index = array_pop($path);
        $siblings = &$this->siblingsAt($path);
        array_splice($siblings, $index + 1, 0, [$duplicate]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function duplicateFieldsTree(array $fields): array
    {
        return array_map(function (array $field): array {
            $field['_uid'] = (string) Str::uuid();
            $field['replicator_fields'] = $this->duplicateFieldsTree($field['replicator_fields'] ?? []);

            return $field;
        }, $fields);
    }

    /**
     * Drag-and-drop reorder handler for wire:sort (see field-editor.blade.php
     * and the tab/section field lists in blueprint-manager.blade.php). $id is
     * the dragged field's stable "_uid" (used only by wire:sort:item to
     * identify which field moved — the field-editor's own wire:key is
     * position-based instead, deliberately NOT the field's _uid: Flux's form
     * controls are custom elements with no attributeChangedCallback, so
     * morphing a keyed-move of the whole field panel left stale values
     * behind after a drag; keeping the key tied to the field's slot instead
     * makes Livewire patch that slot's content in place — the well-trodden
     * code path — at the cost of a field's collapsed/expanded state now
     * following its slot rather than the field itself across a reorder).
     * $position is the dragged field's new zero-based position within that
     * list. $groupId is JSON describing which list: {"tab":...,"section":...}
     * for a top-level tab/section group, or {"parentPath":[...]} for a
     * replicator's fields.
     */
    public function reorderFields(string $id, int $position, string $groupId): void
    {
        $group = json_decode($groupId, true) ?? [];
        $parentPath = $group['parentPath'] ?? [];

        if ($parentPath === []) {
            $index = $this->findFieldIndexByUid($this->fields, $id);

            if ($index === null) {
                return;
            }

            $this->reorderTopLevelFields($index, $position, $group['tab'] ?? 'main', $group['section'] ?? '');

            return;
        }

        $siblings = &$this->siblingsAt($parentPath);
        $oldIndex = $this->findFieldIndexByUid($siblings, $id);

        if ($oldIndex === null) {
            return;
        }

        $values = array_values($siblings);
        $moved = array_splice($values, $oldIndex, 1)[0];
        array_splice($values, $position, 0, [$moved]);
        $siblings = $values;
    }

    protected function findFieldIndexByUid(array $fields, string $uid): ?int
    {
        foreach ($fields as $index => $field) {
            if (($field['_uid'] ?? null) === $uid) {
                return $index;
            }
        }

        return null;
    }

    /**
     * Reorder within a tab/section group without disturbing other groups'
     * positions in the flat $fields array — the absolute index slots a
     * group occupies stay fixed, only the values assigned to them change,
     * mirroring moveTopLevelField()'s swap-within-group approach.
     */
    protected function reorderTopLevelFields(int $index, int $position, string $tab, string $section): void
    {
        if (! array_key_exists($index, $this->fields)) {
            return;
        }

        // Dropping into a different tab/section moves the field there —
        // update it before computing the group, so a cross-group drag
        // relocates the field instead of being silently ignored.
        $this->fields[$index]['tab'] = $tab;
        $this->fields[$index]['section'] = $section;

        $groupIndexes = array_values(array_filter(
            array_keys($this->fields),
            fn (int $i): bool => ($this->fields[$i]['tab'] ?? 'main') === $tab && ($this->fields[$i]['section'] ?? '') === $section
        ));

        $oldPosition = array_search($index, $groupIndexes, true);

        if ($oldPosition === false) {
            return;
        }

        $orderedValues = array_map(fn (int $i): array => $this->fields[$i], $groupIndexes);
        $moved = array_splice($orderedValues, $oldPosition, 1)[0];
        $position = max(0, min($position, count($orderedValues)));
        array_splice($orderedValues, $position, 0, [$moved]);

        foreach ($groupIndexes as $slotPosition => $absoluteIndex) {
            $this->fields[$absoluteIndex] = $orderedValues[$slotPosition];
        }
    }

    public function moveFieldUp(array $path): void
    {
        if (count($path) === 1) {
            $this->moveTopLevelField($path[0], -1);

            return;
        }

        $index = array_pop($path);

        if ($index === 0) {
            return;
        }

        $this->swapFieldsAt($path, $index, $index - 1);
    }

    public function moveFieldDown(array $path): void
    {
        if (count($path) === 1) {
            $this->moveTopLevelField($path[0], 1);

            return;
        }

        $index = array_pop($path);
        $siblings = &$this->siblingsAt($path);

        if ($index === count($siblings) - 1) {
            return;
        }

        $this->swapFieldsAt($path, $index, $index + 1);
    }

    /**
     * Top-level fields are grouped into tabs (and, within a tab, sections)
     * in the UI, so "move up/down" there reorders within the field's own
     * group — swapping with the flat array's adjacent index would
     * otherwise jump across tabs or sections.
     */
    protected function moveTopLevelField(int $index, int $direction): void
    {
        $tab = $this->fields[$index]['tab'] ?? 'main';
        $section = $this->fields[$index]['section'] ?? '';

        $sameGroupIndexes = array_values(array_filter(
            array_keys($this->fields),
            fn (int $i): bool => ($this->fields[$i]['tab'] ?? 'main') === $tab && ($this->fields[$i]['section'] ?? '') === $section
        ));

        $position = array_search($index, $sameGroupIndexes, true);
        $swapPosition = $position + $direction;

        if ($swapPosition < 0 || $swapPosition >= count($sameGroupIndexes)) {
            return;
        }

        $otherIndex = $sameGroupIndexes[$swapPosition];
        [$this->fields[$index], $this->fields[$otherIndex]] = [$this->fields[$otherIndex], $this->fields[$index]];
    }

    /**
     * Reference to the field at $path, walking into "replicator_fields" one
     * level per remaining path segment after the first.
     */
    protected function &fieldAt(array $path): array
    {
        $index = array_shift($path);
        $field = &$this->fields[$index];

        foreach ($path as $subIndex) {
            $field = &$field['replicator_fields'][$subIndex];
        }

        return $field;
    }

    /**
     * Reference to the sibling array a field at $parentPath lives in — the
     * root $fields array itself when $parentPath is empty, otherwise that
     * field's "replicator_fields".
     */
    protected function &siblingsAt(array $parentPath): array
    {
        if ($parentPath === []) {
            return $this->fields;
        }

        $field = &$this->fieldAt($parentPath);

        return $field['replicator_fields'];
    }

    protected function swapFieldsAt(array $parentPath, int $a, int $b): void
    {
        $siblings = &$this->siblingsAt($parentPath);
        [$siblings[$a], $siblings[$b]] = [$siblings[$b], $siblings[$a]];
    }

    /**
     * Reset a field's default value whenever its type changes, since the
     * default-value control (text / true-false radio / options select)
     * differs per type and a leftover value from the previous type would
     * no longer make sense. Works at any nesting depth.
     */
    public function updated(string $name): void
    {
        if (preg_match('/^fields\.\d+(?:\.replicator_fields\.\d+)*\.type$/', $name)) {
            $field = &$this->fieldAt($this->extractPath($name, '.type'));
            $type = $field['type'];

            $field['default'] = '';

            if (in_array($type, ['toggle', 'date', 'replicator', 'assets', 'fieldset', 'taxonomy', 'author'], true)) {
                $field['placeholder'] = '';
            }

            if ($type !== 'replicator') {
                $field['replicator_fields'] = [];
            }

            if (! in_array($type, [...self::$optionBasedTypes, 'assets', 'link', 'fieldset', 'taxonomy'], true)) {
                $field['options'] = '';
            }

            return;
        }

        if (preg_match('/^fields\.(\d+)\.tab$/', $name, $matches)) {
            $this->fields[(int) $matches[1]]['section'] = '';

            return;
        }

        if (preg_match('/^fields\.\d+(?:\.replicator_fields\.\d+)*\.condition_mode$/', $name)) {
            $field = &$this->fieldAt($this->extractPath($name, '.condition_mode'));

            if ($field['condition_mode'] === 'always') {
                $field['condition_field'] = '';
                $field['condition_operator'] = 'equals';
                $field['condition_value'] = '';
            }
        }
    }

    /**
     * Turn a dotted property name like
     * "fields.0.replicator_fields.2.replicator_fields.1.type" into the
     * index path [0, 2, 1] used by fieldAt()/siblingsAt().
     */
    protected function extractPath(string $name, string $suffix): array
    {
        $trimmed = substr($name, 0, -strlen($suffix));

        return array_values(array_map(
            'intval',
            array_filter(explode('.', $trimmed), fn (string $segment): bool => ctype_digit($segment))
        ));
    }

    protected function ruleStringError(string $ruleString): ?string
    {
        if ($ruleString === '') {
            return null;
        }

        try {
            ValidatorFacade::make(['value' => 'sample'], ['value' => $ruleString])->passes();

            return null;
        } catch (Throwable) {
            return __('Unknown or invalid Laravel validation rule.');
        }
    }

    /**
     * When a field is set to "show when" / "hide when", it must point at
     * another field (by handle) within its own scope — the blueprint for a
     * top-level field, or its parent replicator's own sub-fields for a
     * nested one — with an operator and a comparison value, otherwise the
     * condition can never evaluate.
     *
     * @param  array<int, string>  $scopeHandles
     * @return array<string, string>
     */
    protected function conditionErrors(array $field, array $scopeHandles): array
    {
        if ($field['condition_mode'] === 'always') {
            return [];
        }

        $errors = [];

        if (($field['condition_field'] ?? '') === '') {
            $errors['condition_field'] = __('Select a field to base this condition on.');
        } elseif ($field['condition_field'] === $field['handle']) {
            $errors['condition_field'] = __('A field cannot depend on its own condition.');
        } elseif (! in_array($field['condition_field'], $scopeHandles, true)) {
            $errors['condition_field'] = __('That field does not exist in this scope.');
        }

        if (($field['condition_operator'] ?? '') === '') {
            $errors['condition_operator'] = __('Select an operator for this condition.');
        }

        if (($field['condition_value'] ?? '') === '') {
            $errors['condition_value'] = __('Enter a value to compare against.');
        }

        return $errors;
    }

    /**
     * Fill in any missing field keys with their addField() defaults, so a
     * partially-built $fields array (e.g. from a bulk ->set() call) is still
     * safe to validate and render instead of throwing on undefined keys.
     */
    protected function fieldDefaults(): array
    {
        return [
            'handle' => '', 'display' => '', 'type' => 'text', 'required' => false, 'localizable' => false, 'options' => '',
            'placeholder' => '', 'default' => '', 'validation' => '', 'instructions' => '', 'sometimes' => false,
            'condition_mode' => 'always', 'condition_field' => '', 'condition_operator' => 'equals', 'condition_value' => '',
            'min_items' => '', 'max_items' => '', 'collapsed_by_default' => false, 'toolbar' => [], 'tab' => 'main', 'section' => '',
            'replicator_fields' => [],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function normalizeFieldsTree(array $fields): array
    {
        return array_map(function (array $field): array {
            $field = array_merge($this->fieldDefaults(), $field);
            $field['replicator_fields'] = $this->normalizeFieldsTree($field['replicator_fields'] ?? []);

            return $field;
        }, $fields);
    }

    protected function fieldValidationRules(bool $requireHandle = true): array
    {
        return [
            'handle' => $requireHandle
                ? ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', Rule::notIn(['title'])]
                : ['nullable', 'string', 'max:255'],
            // A "fieldset" field has no label/handle inputs of its own (see
            // field-editor.blade.php) — it just imports another fieldset's
            // fields — so $requireHandle (false only for that type) doubles
            // as "does this field even have a label to require".
            'display' => [$requireHandle ? 'required' : 'nullable', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(static::$fieldtypes))],
            'required' => ['boolean'],
            'localizable' => ['boolean'],
            'options' => ['nullable', 'string'],
            'placeholder' => ['nullable', 'string', 'max:255'],
            'default' => ['nullable', 'string', 'max:255'],
            'validation' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_:,|.\-]*$/'],
            'instructions' => ['nullable', 'string', 'max:1000'],
            'sometimes' => ['boolean'],
            'condition_mode' => ['required', Rule::in(static::$conditionModes)],
            'condition_field' => ['nullable', 'string', 'max:255'],
            'condition_operator' => ['nullable', Rule::in(array_keys(static::$conditionOperators))],
            'condition_value' => ['nullable', 'string', 'max:255'],
            'min_items' => ['nullable', 'integer', 'min:0'],
            'max_items' => ['nullable', 'integer', 'min:0'],
            'collapsed_by_default' => ['boolean'],
            'toolbar' => ['array'],
            'toolbar.*' => [Rule::in(array_keys(static::$bardToolbarButtons))],
            'tab' => ['nullable', 'string'],
            'section' => ['nullable', 'string'],
        ];
    }

    /**
     * Validate a level of the field tree (and recurse into every
     * replicator's own sub-fields), returning dotted-path errors keyed the
     * same way Livewire's own $this->validate() would. Recursion is what
     * lets a replicator contain a replicator to any depth — a fixed set of
     * Laravel wildcard rules can't express an unknown nesting depth.
     *
     * @return array<string, string>
     */
    protected function validateFieldsTree(array $fields, string $prefix, array $tabHandles = [], array $sectionHandlesByTab = []): array
    {
        $errors = [];
        $handles = array_column(array_filter($fields, fn (array $field): bool => $field['type'] !== 'fieldset'), 'handle');

        if (count($handles) !== count(array_unique($handles))) {
            $errors[$prefix] = __('Field handles must be unique within this level.');
        }

        foreach ($fields as $index => $field) {
            $errorKey = "{$prefix}.{$index}";

            $validator = ValidatorFacade::make($field, $this->fieldValidationRules($field['type'] !== 'fieldset'), [
                'handle.regex' => __('Field handle must be lowercase letters, numbers and underscores, starting with a letter.'),
                'handle.not_in' => __('"title" is a reserved handle — it is already a default field on every entry.'),
                'validation.regex' => __('Validation rules may only contain letters, numbers and : , | . _ -'),
            ]);

            foreach ($validator->errors()->toArray() as $attribute => $messages) {
                $errors["{$errorKey}.{$attribute}"] = $messages[0];
            }

            if (
                ! isset($errors["{$errorKey}.default"])
                && ($field['default'] ?? '') !== ''
                && $field['type'] === 'date'
                && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $field['default'])
            ) {
                $errors["{$errorKey}.default"] = __('Default value must be a valid date (YYYY-MM-DD).');
            }

            if (! isset($errors["{$errorKey}.validation"]) && $message = $this->ruleStringError($field['validation'] ?? '')) {
                $errors["{$errorKey}.validation"] = $message;
            }

            if (! isset($errors["{$errorKey}.condition_mode"])) {
                foreach ($this->conditionErrors($field, $handles) as $key => $message) {
                    $errors["{$errorKey}.{$key}"] = $message;
                }
            }

            if ($field['type'] === 'assets' && ! isset($errors["{$errorKey}.options"])) {
                $container = trim($field['options'] ?? '');

                if ($container === '') {
                    $errors["{$errorKey}.options"] = __('Select an asset container.');
                } elseif (! in_array($container, $this->availableAssetContainers->pluck('handle')->all(), true)) {
                    $errors["{$errorKey}.options"] = __('That asset container does not exist.');
                }
            }

            if ($field['type'] === 'taxonomy' && ! isset($errors["{$errorKey}.options"])) {
                $taxonomyHandle = trim($field['options'] ?? '');

                if ($taxonomyHandle === '') {
                    $errors["{$errorKey}.options"] = __('Select a taxonomy.');
                } elseif (! in_array($taxonomyHandle, $this->availableTaxonomies->pluck('handle')->all(), true)) {
                    $errors["{$errorKey}.options"] = __('That taxonomy does not exist.');
                }
            }

            if ($field['type'] === 'fieldset' && ! isset($errors["{$errorKey}.options"])) {
                $fieldsetHandle = trim($field['options'] ?? '');

                if ($fieldsetHandle === '') {
                    $errors["{$errorKey}.options"] = __('Select a fieldset.');
                } elseif (! in_array($fieldsetHandle, $this->availableFieldsets->pluck('handle')->all(), true)) {
                    $errors["{$errorKey}.options"] = __('That fieldset does not exist.');
                }
            }

            if ($field['type'] === 'link' && ! isset($errors["{$errorKey}.options"])) {
                $validHandles = $this->availableCollections->pluck('handle')->all();

                foreach (explode(',', $field['options'] ?? '') as $collectionHandle) {
                    $collectionHandle = trim($collectionHandle);

                    if ($collectionHandle !== '' && ! in_array($collectionHandle, $validHandles, true)) {
                        $errors["{$errorKey}.options"] = __('":handle" is not a valid collection.', ['handle' => $collectionHandle]);
                        break;
                    }
                }
            }

            if ($tabHandles !== [] && ! in_array($field['tab'] ?? 'main', $tabHandles, true)) {
                $errors["{$errorKey}.tab"] = __('That tab does not exist.');
            } elseif (
                $tabHandles !== []
                && ($field['section'] ?? '') !== ''
                && ! in_array($field['section'], $sectionHandlesByTab[$field['tab'] ?? 'main'] ?? [], true)
            ) {
                $errors["{$errorKey}.section"] = __('That section does not exist in this tab.');
            }

            if (
                in_array($field['type'], ['assets', 'checkbox', 'taxonomy', 'replicator'], true)
                && ! isset($errors["{$errorKey}.max_items"])
                && ($field['min_items'] ?? '') !== ''
                && ($field['max_items'] ?? '') !== ''
                && (int) $field['max_items'] < (int) $field['min_items']
            ) {
                $errors["{$errorKey}.max_items"] = __('Maximum selections must be greater than or equal to minimum selections.');
            }

            if ($field['type'] === 'replicator') {
                if (empty($field['replicator_fields'])) {
                    $errors["{$errorKey}.replicator_fields"] = __('Add at least one field to the replicator.');
                } else {
                    $errors = array_merge($errors, $this->validateFieldsTree($field['replicator_fields'], "{$errorKey}.replicator_fields"));
                }
            }
        }

        return $errors;
    }

    protected function normalizeFieldForStorage(array $field, bool $topLevel = true): array
    {
        $normalized = [
            'handle' => $field['handle'],
            'display' => $field['display'] ?: Str::headline($field['handle']),
            'type' => $field['type'],
            'required' => (bool) $field['required'],
            'localizable' => (bool) ($field['localizable'] ?? false),
            'placeholder' => in_array($field['type'], ['toggle', 'date', 'replicator', 'assets', 'fieldset', 'taxonomy', 'author'], true)
                ? null
                : (($field['placeholder'] ?? '') ?: null),
            'default' => match (true) {
                in_array($field['type'], ['replicator', 'assets', 'link', 'fieldset', 'taxonomy', 'author'], true) => null,
                $field['type'] === 'toggle' => ($field['default'] ?? 'false') === 'true',
                default => ($field['default'] ?? '') ?: null,
            },
            'validation' => ($field['validation'] ?? '') ?: null,
            'instructions' => ($field['instructions'] ?? '') ?: null,
            'validate_sometimes' => (bool) ($field['sometimes'] ?? false),
            'conditions' => $field['condition_mode'] !== 'always' ? [
                'mode' => $field['condition_mode'],
                'field' => $field['condition_field'],
                'operator' => $field['condition_operator'],
                'value' => $field['condition_value'],
            ] : null,
        ];

        if (in_array($field['type'], static::$optionBasedTypes, true)) {
            $normalized['options'] = collect(explode(',', $field['options'] ?? ''))
                ->map(fn (string $option): string => trim($option))
                ->filter()
                ->values()
                ->all();
        }

        if ($field['type'] === 'checkbox') {
            $normalized['default'] = collect(explode(',', $field['default'] ?? ''))
                ->map(fn (string $value): string => trim($value))
                ->filter()
                ->values()
                ->all();
        }

        if ($field['type'] === 'assets' || $field['type'] === 'fieldset' || $field['type'] === 'taxonomy') {
            $normalized['options'] = trim($field['options'] ?? '') ?: null;
        }

        if ($field['type'] === 'link') {
            $normalized['options'] = collect(explode(',', $field['options'] ?? ''))
                ->map(fn (string $collectionHandle): string => trim($collectionHandle))
                ->filter()
                ->values()
                ->all();
        }

        if (in_array($field['type'], ['assets', 'checkbox', 'taxonomy', 'replicator'], true)) {
            $normalized['min_items'] = ($field['min_items'] ?? '') !== '' ? (int) $field['min_items'] : null;
            $normalized['max_items'] = ($field['max_items'] ?? '') !== '' ? (int) $field['max_items'] : null;
        }

        if ($field['type'] === 'bard') {
            $normalized['toolbar'] = array_values($field['toolbar'] ?? []);
        }

        if ($field['type'] === 'replicator') {
            $normalized['collapsed_by_default'] = (bool) ($field['collapsed_by_default'] ?? false);
            $normalized['replicator_fields'] = array_map(
                fn (array $sub): array => $this->normalizeFieldForStorage($sub, topLevel: false),
                $field['replicator_fields'] ?? []
            );
        }

        if ($topLevel) {
            $normalized['tab'] = $field['tab'] ?? 'main';
            $normalized['section'] = ($field['section'] ?? '') ?: null;
        }

        return $normalized;
    }
}
