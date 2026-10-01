<?php

namespace App\Modules\Sites;

use App\Modules\Sites\Console\Commands\CreateDefaultSiteCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Multi-site configuration.
 * Owns tables: sites.
 */
class SitesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'sites');

        $this->commands([
            CreateDefaultSiteCommand::class,
        ]);

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('sites.')
            ->group(__DIR__.'/routes.php');
    }
}
