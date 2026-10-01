<?php

namespace App\Modules\Forms\Http\Controllers;

use App\Modules\Blueprints\Support\EvaluatesFieldConditions;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Handles the actual <form method="POST"> submission for a Form — the
 * front-end markup is hand-built by the site's own templates (see
 * Form::fields()), this just validates against the form's blueprint fields
 * and stores the result, mirroring Statamic's generic form-submit route.
 */
class FormSubmissionController
{
    use EvaluatesFieldConditions;

    public function store(Request $request, Form $form): RedirectResponse
    {
        $validated = $request->validate(
            $this->rulesFor($form->fields(), (array) $request->input('data', [])),
            [],
            $this->attributesFor($form->fields())
        );

        if ($form->storesSubmissions()) {
            FormSubmission::create([
                'form' => $form->handle,
                'data' => $validated['data'] ?? [],
            ]);
        }

        $redirectUrl = $form->redirectUrl();

        return $redirectUrl
            ? redirect($redirectUrl)->with('form_submitted', $form->handle)
            : back()->with('form_submitted', $form->handle);
    }

    /**
     * @param  array<int, array<string, mixed>>  $fields
     * @param  array<string, mixed>  $submittedData
     * @return array<string, mixed>
     */
    protected function rulesFor(array $fields, array $submittedData = []): array
    {
        $rules = [];

        foreach ($fields as $field) {
            $key = "data.{$field['handle']}";

            if (! $this->fieldIsVisible($field, $submittedData)) {
                $rules[$key] = ['nullable'];

                continue;
            }

            $fieldRules = [($field['required'] ?? false) ? 'required' : 'nullable'];

            $fieldRules[] = match ($field['type']) {
                'number' => 'numeric',
                'toggle' => 'boolean',
                'date' => 'date',
                'select', 'radio' => Rule::in($field['options'] ?? []),
                'checkbox' => 'array',
                default => 'string',
            };

            if (! empty($field['validation'])) {
                $fieldRules[] = $field['validation'];
            }

            $rules[$key] = $fieldRules;

            if ($field['type'] === 'checkbox') {
                $rules["{$key}.*"] = [Rule::in($field['options'] ?? [])];
            }
        }

        return $rules;
    }

    /**
     * Maps each "data.*" validation key to its field's display label, so a
     * validation message reads "The Email field is required." instead of
     * "The data.email field is required." — mirrors the same fix applied to
     * every blueprint-driven Livewire form.
     *
     * @param  array<int, array<string, mixed>>  $fields
     * @return array<string, string>
     */
    protected function attributesFor(array $fields): array
    {
        return collect($fields)->mapWithKeys(fn (array $field): array => [
            "data.{$field['handle']}" => $field['display'] ?? $field['handle'],
        ])->all();
    }
}
