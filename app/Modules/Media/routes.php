<?php

// Routes for the Media module.
// Registered by MediaServiceProvider under the CMS prefix (config('cms.route')),
// 'web' + 'auth' + 'verified' middleware, and the 'media.' route name prefix.
// Do NOT add ->prefix()/->middleware()/->name() again in this file.

use Illuminate\Support\Facades\Route;

Route::livewire('media', 'media::index')->name('index');
