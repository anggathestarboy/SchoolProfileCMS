<?php

use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;

function makeConditionalContactBlueprint(): Blueprint
{
    return Blueprint::create([
        'namespace' => null,
        'handle' => 'contact',
        'data' => ['fields' => [
            ['handle' => 'wants_callback', 'display' => 'Wants Callback', 'type' => 'toggle', 'required' => false, 'default' => false],
            ['handle' => 'phone', 'display' => 'Phone', 'type' => 'text', 'required' => true, 'conditions' => ['mode' => 'show', 'field' => 'wants_callback', 'operator' => 'equals', 'value' => '1']],
        ]],
    ]);
}

test('a conditionally hidden required field is not enforced on public form submission', function () {
    makeConditionalContactBlueprint();
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);

    $this->post('/!/forms/contact', [
        'data' => ['wants_callback' => '0'],
    ])->assertSessionHasNoErrors();

    expect(FormSubmission::where('form', 'contact')->exists())->toBeTrue();
});

test('a required field is still enforced on public form submission once its condition matches', function () {
    makeConditionalContactBlueprint();
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);

    $this->post('/!/forms/contact', [
        'data' => ['wants_callback' => '1'],
    ])->assertSessionHasErrors(['data.phone']);

    expect(FormSubmission::where('form', 'contact')->exists())->toBeFalse();
});
