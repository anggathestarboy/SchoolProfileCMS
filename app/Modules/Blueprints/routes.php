<?php

// Routes for the Blueprints module.
// Registered by BlueprintsServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'blueprints.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\Blueprints\Livewire\BlueprintForm;
use App\Modules\Blueprints\Livewire\BlueprintManager;
use App\Modules\Blueprints\Livewire\FieldsetForm;
use App\Modules\Blueprints\Livewire\FieldsetManager;
use Illuminate\Support\Facades\Route;

Route::get('blueprints', BlueprintManager::class)->name('index');
Route::get('blueprints/create', BlueprintForm::class)->name('create');
Route::get('blueprints/{blueprint}/edit', BlueprintForm::class)->name('edit');
Route::get('fieldsets', FieldsetManager::class)->name('fieldsets');
Route::get('fieldsets/create', FieldsetForm::class)->name('fieldsets.create');
Route::get('fieldsets/{fieldset}/edit', FieldsetForm::class)->name('fieldsets.edit');
