<?php

// Routes for the Collections module.
// Registered by CollectionsServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'collections.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\Collections\Http\Controllers\PreviewController;
use App\Modules\Collections\Livewire\CollectionManager;
use App\Modules\Collections\Livewire\EntryForm;
use App\Modules\Collections\Livewire\EntryManager;
use Illuminate\Support\Facades\Route;

Route::get('collections', CollectionManager::class)->name('index');
Route::get('collections/{collection:handle}', EntryManager::class)->name('entries');
Route::get('collections/{collection:handle}/entries/create/{site}', EntryForm::class)->name('entries.create');
Route::get('collections/{collection:handle}/entries/{entry}', EntryForm::class)->name('entries.edit');
Route::get('preview/{token}', [PreviewController::class, 'show'])->name('preview');
