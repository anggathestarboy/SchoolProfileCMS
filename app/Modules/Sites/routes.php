<?php

// Routes for the Sites module.
// Registered by SitesServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'sites.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\Sites\Livewire\SiteManager;
use Illuminate\Support\Facades\Route;

Route::get('sites', SiteManager::class)->name('sites.index');
