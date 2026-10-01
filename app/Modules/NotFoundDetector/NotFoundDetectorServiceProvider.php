<?php

namespace App\Modules\NotFoundDetector;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * On-demand scan for published entries whose public URL 404s.
 * Owns no tables — every scan reads entries/collections fresh.
 */
class NotFoundDetectorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'not-found-detector');

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('not-found-detector.')
            ->group(__DIR__.'/routes.php');
    }
}
