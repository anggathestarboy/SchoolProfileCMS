<?php

use App\Models\User;
use App\Modules\Blueprints\Models\Blueprint;
use App\Modules\Forms\Livewire\FormManager;
use App\Modules\Forms\Livewire\FormSubmissionManager;
use App\Modules\Forms\Models\Form;
use App\Modules\Forms\Models\FormSubmission;
use App\Modules\Media\Models\AssetContainer;
use App\Modules\Media\Models\AssetMeta;
use App\Modules\Users\Models\Role;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function makeContactBlueprint(): Blueprint
{
    return Blueprint::create([
        'namespace' => null,
        'handle' => 'contact',
        'data' => ['fields' => [
            ['handle' => 'name', 'display' => 'Name', 'type' => 'text', 'required' => true, 'localizable' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
            ['handle' => 'email', 'display' => 'Email', 'type' => 'text', 'required' => true, 'localizable' => false, 'placeholder' => null, 'default' => null, 'instructions' => null, 'validation' => 'email'],
            ['handle' => 'message', 'display' => 'Message', 'type' => 'textarea', 'required' => false, 'localizable' => false, 'placeholder' => null, 'default' => null, 'instructions' => null],
        ]],
    ]);
}

test('forms page is displayed', function () {
    $this->actingAs(User::factory()->create());

    $this->get('/admin-panel/forms')->assertOk();
});

test('a user without the configure forms permission cannot view the forms page', function () {
    $user = User::factory()->create();
    $role = Role::create(['handle' => 'restricted', 'title' => 'Restricted', 'permissions' => ['access cp']]);
    $user->roles()->attach($role);
    $this->actingAs($user);

    $this->get('/admin-panel/forms')->assertForbidden();
});

test('a form can be created with a handle-generate button and picks an existing blueprint', function () {
    $this->actingAs(User::factory()->create());
    $blueprint = makeContactBlueprint();

    Livewire::test(FormManager::class)
        ->call('create')
        ->set('title', 'Contact Us')
        ->call('regenerateHandle')
        ->assertSet('handle', 'contact_us')
        ->set('createBlueprint', false)
        ->set('blueprint', $blueprint->handle)
        ->call('save')
        ->assertHasNoErrors();

    $form = Form::where('handle', 'contact_us')->sole();

    expect($form->title)->toBe('Contact Us');
    expect($form->effectiveBlueprint()->handle)->toBe('contact');
    expect($form->storesSubmissions())->toBeTrue();
});

test('form creation defaults to auto-creating its own blueprint, namespaced to the form', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FormManager::class)
        ->set('title', 'Contact Us')
        ->set('handle', 'contact_us')
        ->call('save')
        ->assertHasNoErrors();

    $form = Form::sole();

    expect($form->settings['blueprint'])->toBeNull();

    $blueprint = Blueprint::where('namespace', 'forms.contact_us')->sole();

    expect($blueprint->handle)->toBe('contact_us');
    expect($form->effectiveBlueprint()->id)->toBe($blueprint->id);
});

test('a form can turn off storing submissions and set a redirect url', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(FormManager::class)
        ->set('title', 'Newsletter')
        ->set('handle', 'newsletter')
        ->set('storeSubmissions', false)
        ->set('redirectUrl', 'https://example.com/thanks')
        ->call('save')
        ->assertHasNoErrors();

    $form = Form::sole();

    expect($form->storesSubmissions())->toBeFalse();
    expect($form->redirectUrl())->toBe('https://example.com/thanks');
});

test('a form with existing submissions cannot be deleted', function () {
    $this->actingAs(User::factory()->create());

    $form = Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => []]);
    FormSubmission::create(['form' => 'contact', 'data' => ['name' => 'Jane']]);

    Livewire::test(FormManager::class)->call('delete', $form->id);

    expect(Form::find($form->id))->not->toBeNull();
});

test('a form with no submissions can be deleted', function () {
    $this->actingAs(User::factory()->create());

    $form = Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => []]);

    Livewire::test(FormManager::class)->call('delete', $form->id);

    expect(Form::find($form->id))->toBeNull();
});

test('the submissions page lists a column per blueprint field and shows submitted values', function () {
    $this->actingAs(User::factory()->create());
    makeContactBlueprint();
    $form = Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);

    FormSubmission::create(['form' => 'contact', 'data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hi there']]);

    Livewire::test(FormSubmissionManager::class, ['form' => $form])
        ->assertSee('Jane Doe')
        ->assertSee('jane@example.com')
        ->assertSee('Hi there');
});

test('a submission can be deleted from the submissions page', function () {
    $this->actingAs(User::factory()->create());
    makeContactBlueprint();
    $form = Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);
    $submission = FormSubmission::create(['form' => 'contact', 'data' => ['name' => 'Jane Doe']]);

    Livewire::test(FormSubmissionManager::class, ['form' => $form])
        ->call('delete', $submission->id);

    expect(FormSubmission::find($submission->id))->toBeNull();
});

test('a form can choose which fields show as columns in the submissions table', function () {
    $this->actingAs(User::factory()->create());
    $blueprint = makeContactBlueprint();

    Livewire::test(FormManager::class)
        ->set('title', 'Contact Us')
        ->set('handle', 'contact_us')
        ->set('createBlueprint', false)
        ->set('blueprint', $blueprint->handle)
        ->set('previewFields', ['name', 'email'])
        ->call('save')
        ->assertHasNoErrors();

    $form = Form::where('handle', 'contact_us')->sole();

    expect($form->previewFieldHandles())->toBe(['name', 'email']);
});

test('the submissions table only shows the configured preview columns when set', function () {
    $this->actingAs(User::factory()->create());
    makeContactBlueprint();
    $form = Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact', 'preview_fields' => ['name']]]);
    FormSubmission::create(['form' => 'contact', 'data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hi there']]);

    $fields = Livewire::test(FormSubmissionManager::class, ['form' => $form])->instance()->fields();

    expect(collect($fields)->pluck('handle')->all())->toBe(['name']);
});

test('the submissions table shows every field when no preview columns are configured', function () {
    $this->actingAs(User::factory()->create());
    makeContactBlueprint();
    $form = Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);

    $fields = Livewire::test(FormSubmissionManager::class, ['form' => $form])->instance()->fields();

    expect(collect($fields)->pluck('handle')->all())->toBe(['name', 'email', 'message']);
});

test('the submission detail page shows every field regardless of preview column configuration', function () {
    $this->actingAs(User::factory()->create());
    makeContactBlueprint();
    $form = Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact', 'preview_fields' => ['name']]]);
    $submission = FormSubmission::create(['form' => 'contact', 'data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hi there']]);

    $this->get("/admin-panel/forms/contact/submissions/{$submission->id}")
        ->assertOk()
        ->assertSee('Jane Doe')
        ->assertSee('jane@example.com')
        ->assertSee('Hi there');
});

test('a submission from another form cannot be viewed through a mismatched form handle', function () {
    $this->actingAs(User::factory()->create());
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => []]);
    Form::create(['handle' => 'newsletter', 'title' => 'Newsletter', 'settings' => []]);
    $submission = FormSubmission::create(['form' => 'contact', 'data' => ['name' => 'Jane Doe']]);

    $this->get("/admin-panel/forms/newsletter/submissions/{$submission->id}")->assertNotFound();
});

test('a submission can be exported as txt', function () {
    $this->actingAs(User::factory()->create());
    makeContactBlueprint();
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);
    $submission = FormSubmission::create(['form' => 'contact', 'data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hi there']]);

    $this->get("/admin-panel/forms/contact/submissions/{$submission->id}/export/txt")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Name: Jane Doe', false)
        ->assertSee('Email: jane@example.com', false);
});

test('a submission can be exported as json', function () {
    $this->actingAs(User::factory()->create());
    makeContactBlueprint();
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);
    $submission = FormSubmission::create(['form' => 'contact', 'data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com']]);

    $this->get("/admin-panel/forms/contact/submissions/{$submission->id}/export/json")
        ->assertOk()
        ->assertJson(['name' => 'Jane Doe', 'email' => 'jane@example.com']);
});

test('submitting the public form endpoint validates required fields and shows friendly labels', function () {
    makeContactBlueprint();
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);

    $this->post('/!/forms/contact', ['data' => ['message' => 'Hi']])
        ->assertSessionHasErrors(['data.name', 'data.email']);
});

test('submitting the public form endpoint stores a submission and redirects back', function () {
    makeContactBlueprint();
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact']]);

    $response = $this->from('/some-page')->post('/!/forms/contact', [
        'data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hi there'],
    ]);

    $response->assertRedirect('/some-page');
    $response->assertSessionHas('form_submitted', 'contact');

    $submission = FormSubmission::sole();

    expect($submission->form)->toBe('contact');
    expect($submission->data)->toBe(['name' => 'Jane Doe', 'email' => 'jane@example.com', 'message' => 'Hi there']);
});

test('submitting a form configured to not store submissions still succeeds without persisting one', function () {
    makeContactBlueprint();
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact', 'store_submissions' => false]]);

    $this->from('/some-page')->post('/!/forms/contact', [
        'data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
    ])->assertRedirect('/some-page');

    expect(FormSubmission::count())->toBe(0);
});

test('submitting a form with a redirect url configured redirects there on success', function () {
    makeContactBlueprint();
    Form::create(['handle' => 'contact', 'title' => 'Contact', 'settings' => ['blueprint' => 'contact', 'redirect_url' => 'https://example.com/thanks']]);

    $this->post('/!/forms/contact', [
        'data' => ['name' => 'Jane Doe', 'email' => 'jane@example.com'],
    ])->assertRedirect('https://example.com/thanks');
});

test('the submissions table links asset field values to the file', function () {
    $this->actingAs(User::factory()->create());

    Storage::fake('public');
    AssetContainer::create(['handle' => 'cv', 'title' => 'CV', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'cv', 'folder' => '', 'basename' => 'resume.pdf', 'filename' => 'resume', 'extension' => 'pdf', 'path' => 'resume.pdf', 'meta' => []]);

    Blueprint::create(['handle' => 'career', 'data' => ['fields' => [
        ['handle' => 'cv_document', 'display' => 'CV Document', 'type' => 'assets'],
    ]]]);
    $form = Form::create(['handle' => 'career', 'title' => 'Career', 'settings' => ['blueprint' => 'career']]);
    FormSubmission::create(['form' => 'career', 'data' => ['cv_document' => $asset->id]]);

    Livewire::test(FormSubmissionManager::class, ['form' => $form])
        ->assertSee($asset->url, false)
        ->assertSee('Resume');
});

test('the submission detail page links asset field values to the file', function () {
    $this->actingAs(User::factory()->create());

    Storage::fake('public');
    AssetContainer::create(['handle' => 'cv', 'title' => 'CV', 'disk' => 'public']);
    $asset = AssetMeta::create(['container' => 'cv', 'folder' => '', 'basename' => 'resume.pdf', 'filename' => 'resume', 'extension' => 'pdf', 'path' => 'resume.pdf', 'meta' => []]);

    Blueprint::create(['handle' => 'career', 'data' => ['fields' => [
        ['handle' => 'cv_document', 'display' => 'CV Document', 'type' => 'assets'],
    ]]]);
    $form = Form::create(['handle' => 'career', 'title' => 'Career', 'settings' => ['blueprint' => 'career']]);
    $submission = FormSubmission::create(['form' => 'career', 'data' => ['cv_document' => $asset->id]]);

    $this->get("/admin-panel/forms/career/submissions/{$submission->id}")
        ->assertOk()
        ->assertSee($asset->url, false)
        ->assertSee('Resume');
});
