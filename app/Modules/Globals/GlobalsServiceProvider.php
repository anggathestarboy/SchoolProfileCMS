<?php

namespace App\Modules\Globals;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Global sets and their per-locale variables.
 * Owns tables: global_sets, global_set_variables.
 */
class GlobalsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'globals');

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('globals.')
            ->group(__DIR__.'/routes.php');
    }
}
