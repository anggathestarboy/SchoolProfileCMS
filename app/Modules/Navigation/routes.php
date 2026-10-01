<?php

// Routes for the Navigation module.
// Registered by NavigationServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'navigation.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\Navigation\Livewire\NavigationManager;
use App\Modules\Navigation\Livewire\NavigationTreeEditor;
use Illuminate\Support\Facades\Route;

Route::get('navigations', NavigationManager::class)->name('index');
Route::get('navigations/{navigation:handle}', NavigationTreeEditor::class)->name('edit');
