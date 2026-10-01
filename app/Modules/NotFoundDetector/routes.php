<?php

// Routes for the 404 Detector module.
// Registered by NotFoundDetectorServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'not-found-detector.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use App\Modules\NotFoundDetector\Livewire\NotFoundDetector;
use Illuminate\Support\Facades\Route;

Route::get('404-detector', NotFoundDetector::class)->name('index');
