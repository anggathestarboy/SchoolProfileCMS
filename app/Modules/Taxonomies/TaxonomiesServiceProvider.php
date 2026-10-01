<?php

namespace App\Modules\Taxonomies;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Taxonomies and their terms (categories/tags shared across collections).
 * Owns tables: taxonomies, taxonomy_terms.
 */
class TaxonomiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'taxonomies');

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('taxonomies.')
            ->group(__DIR__.'/routes.php');
    }
}
