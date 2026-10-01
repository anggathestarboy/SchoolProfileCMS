<?php

// Routes for the Redirects module.
// Registered by RedirectsServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'redirects.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\Redirects\Livewire\RedirectManager;
use Illuminate\Support\Facades\Route;

Route::get('redirect-manager', RedirectManager::class)->name('index');
