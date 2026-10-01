<?php

use App\Modules\Collections\Http\Controllers\PageController;
use App\Modules\Forms\Http\Controllers\FormSubmissionController;
use App\Modules\Sitemap\Http\Controllers\SitemapController;
use App\Modules\SiteSettings\Http\Controllers\SiteSettingsController;
use Illuminate\Support\Facades\Route;

if (config('cms.enabled')) {
    Route::prefix(config('cms.route'))->group(function () {
        Route::get('/', fn () => redirect()->route('dashboard'))->name('cms.home');

        Route::middleware(['auth', 'verified', 'permission:access cp'])->group(function () {
            Route::view('dashboard', 'dashboard')->name('dashboard');
        });
    });
}

require __DIR__.'/settings.php';

// Global (not per-site) robots.txt/llms.txt — see SiteSettingsController.
// Registered ahead of the catch-all page route below, since its "{uri}"
// wildcard would otherwise swallow these paths too.
Route::get('/robots.txt', [SiteSettingsController::class, 'robots'])->name('robots');
Route::get('/llms.txt', [SiteSettingsController::class, 'llms'])->name('llms');

// Public sitemap — split per collection (sitemap index pattern), see
// SitemapController and SitemapSyncer. The per-collection route is
// registered first since "sitemap.xml" would otherwise also match its
// "{collection}.xml" segment.
Route::get('/sitemap-{collection}.xml', [SitemapController::class, 'collection'])->name('sitemap.collection');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap.xsl', [SitemapController::class, 'stylesheet'])->name('sitemap.xsl');

// Public form submission endpoint — the form's own markup is hand-built by
// the site's templates (see App\Modules\Forms\Models\Form::fields()), this
// just validates and stores the POST. Named "forms.submit" (not
// "forms.*", which is reserved for the admin module's own route group).
Route::post('/!/forms/{form:handle}', [FormSubmissionController::class, 'store'])->name('forms.submit');

// Public-facing entry pages, served through the collection route/template
// system — see PageController. Named "home" so the auth layouts' logo link
// (route('home')) still resolves.
Route::get('/', [PageController::class, 'show'])->name('home');

// Excludes "storage/..." and "media/stream/..." so this wildcard doesn't swallow
// them too — unlike the routes above, Laravel's own storage.* routes are only
// registered once every provider has booted (see FilesystemServiceProvider),
// i.e. after this one, so without the exclusion they'd never get a turn to match
// and every private-disk asset (signed storage URLs, streamed video) would 404.
Route::get('/{uri}', [PageController::class, 'show'])
    ->where('uri', '^(?!storage/|media/stream/).*$')
    ->name('page.show');
