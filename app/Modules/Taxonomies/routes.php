<?php

// Routes for the Taxonomies module.
// Registered by TaxonomiesServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'taxonomies.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\Taxonomies\Livewire\TaxonomyManager;
use App\Modules\Taxonomies\Livewire\TermForm;
use App\Modules\Taxonomies\Livewire\TermManager;
use Illuminate\Support\Facades\Route;

Route::get('taxonomies', TaxonomyManager::class)->name('index');
Route::get('taxonomies/{taxonomy:handle}', TermManager::class)->name('terms');
Route::get('taxonomies/{taxonomy:handle}/terms/create/{site}', TermForm::class)->name('terms.create');
Route::get('taxonomies/{taxonomy:handle}/terms/{term:id}', TermForm::class)->name('terms.edit');
