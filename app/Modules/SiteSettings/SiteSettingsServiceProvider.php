<?php

namespace App\Modules\SiteSettings;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Global (not per-site) settings: robots.txt, llms.txt, and the site-wide
 * Organization schema.org JSON-LD.
 * Owns tables: site_settings.
 */
class SiteSettingsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'site-settings');

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('site-settings.')
            ->group(__DIR__.'/routes.php');
    }
}
