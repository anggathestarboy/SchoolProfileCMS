<?php

// Routes for the Globals module.
// Registered by GlobalsServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'globals.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\Globals\Livewire\GlobalSetForm;
use App\Modules\Globals\Livewire\GlobalSetManager;
use Illuminate\Support\Facades\Route;

Route::get('globals', GlobalSetManager::class)->name('index');
Route::get('globals/{globalSet:handle}', GlobalSetForm::class)->name('edit');
