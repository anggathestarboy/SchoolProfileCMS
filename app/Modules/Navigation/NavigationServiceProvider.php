<?php

namespace App\Modules\Navigation;

use App\Modules\Navigation\Console\Commands\SeedAdminSidebarCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Navigation menus.
 * Owns tables: navigations.
 */
class NavigationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'navigation');

        $this->commands([
            SeedAdminSidebarCommand::class,
        ]);

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('navigation.')
            ->group(__DIR__.'/routes.php');
    }
}
