<?php

namespace App\Modules\Redirects;

use App\Modules\Redirects\Http\Middleware\ApplyRedirects;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Redirect manager.
 * Owns tables: redirects.
 */
class RedirectsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'redirects');

        $this->app['router']->pushMiddlewareToGroup('web', ApplyRedirects::class);

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('redirects.')
            ->group(__DIR__.'/routes.php');
    }
}
