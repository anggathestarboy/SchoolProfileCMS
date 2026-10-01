<?php

namespace App\Modules\Structure;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Shared ordering/hierarchy trees used by Collections and Navigation.
 * Owns tables: trees.
 */
class StructureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('structure.')
            ->group(__DIR__.'/routes.php');
    }
}
