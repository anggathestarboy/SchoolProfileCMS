<?php

namespace App\Modules\Sitemap;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Sitemap entries used to generate sitemap.xml.
 * Owns tables: sitemaps.
 */
class SitemapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'sitemap');

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('sitemap.')
            ->group(__DIR__.'/routes.php');
    }
}
