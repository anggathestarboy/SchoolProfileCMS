<?php

namespace App\Modules\Forms;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Form builder and submissions.
 * Owns tables: forms, form_submissions.
 */
class FormsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'forms');

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('forms.')
            ->group(__DIR__.'/routes.php');
    }
}
