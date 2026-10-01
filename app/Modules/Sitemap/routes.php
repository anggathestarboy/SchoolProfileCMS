<?php

// Routes for the Sitemap module.
// Registered by SitemapServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'sitemap.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.
// The public /sitemap.xml route lives in routes/web.php instead, since it
// must NOT sit behind this module's auth+verified middleware.

use App\Modules\Sitemap\Livewire\SitemapManager;
use Illuminate\Support\Facades\Route;

Route::get('sitemap', SitemapManager::class)->name('index');
