<?php

// Routes for the Site Settings module (the admin-panel settings page only —
// the public /robots.txt and /llms.txt routes live in routes/web.php since
// they must NOT sit behind this module's auth+verified middleware).
// Registered by SiteSettingsServiceProvider under the CMS prefix
// (config('cms.route')), 'web' + 'auth' + 'verified' middleware, and the
// 'site-settings.' route name prefix. Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\SiteSettings\Livewire\SiteSettingsManager;
use Illuminate\Support\Facades\Route;

Route::get('site-settings', SiteSettingsManager::class)->name('index');
