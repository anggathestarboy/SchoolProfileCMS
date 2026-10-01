<?php

namespace App\Modules\Forms\Http\Controllers;

use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * Full-page submission detail (see FormSubmissionManager's "View" button)
 * plus TXT/JSON export downloads. PDF export reuses this same page — its
 * "Export PDF" button just calls window.print(), with print:hidden classes
 * on the chrome (see the view), so no server-side PDF rendering is needed.
 */
class SubmissionController
{
    public function show(Form $form, FormSubmission $submission): View
    {
        auth()->user()->authorizePermission('configure forms');
        abort_unless($submission->form === $form->handle, 404);

        return view('forms::submission-detail', [
            'form' => $form,
            'submission' => $submission,
            'fields' => $form->fields(),
        ]);
    }

    public function exportTxt(Form $form, FormSubmission $submission): Response
    {
        auth()->user()->authorizePermission('configure forms');
        abort_unless($submission->form === $form->handle, 404);

        $lines = collect($form->fields())
            ->map(fn (array $field): string => ($field['display'] ?: $field['handle']).': '.$this->formatValue($submission->data[$field['handle']] ?? null))
            ->implode("\n");

        return response($lines, 200, [
            'Content-Type' => 'text/plain',
            'Content-Disposition' => "attachment; filename=\"submission-{$submission->id}.txt\"",
        ]);
    }

    public function exportJson(Form $form, FormSubmission $submission): JsonResponse
    {
        auth()->user()->authorizePermission('configure forms');
        abort_unless($submission->form === $form->handle, 404);

        return response()->json($submission->data)
            ->header('Content-Disposition', "attachment; filename=\"submission-{$submission->id}.json\"");
    }

    protected function formatValue(mixed $value): string
    {
        return is_array($value) ? implode(', ', $value) : (string) $value;
    }
}
