<?php

namespace App\Modules\Blueprints;

use App\Modules\Blueprints\Console\Commands\CreateHomepageBlueprintCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Blueprint and fieldset definitions (the field-schema system used by other modules).
 * Owns tables: blueprints, fieldsets.
 */
class BlueprintsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'blueprints');

        $this->commands([
            CreateHomepageBlueprintCommand::class,
        ]);

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp', 'permission:configure fields'])
            ->prefix(config('cms.route'))
            ->name('blueprints.')
            ->group(__DIR__.'/routes.php');
    }
}
