<?php

namespace App\Modules\Collections;

use App\Modules\Collections\Console\Commands\CreateDefaultPagesCollectionCommand;
use App\Modules\Collections\Console\Commands\CreateHomepageEntryCommand;
use App\Modules\Collections\Console\Commands\PublishScheduledEntriesCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Collection & Entry management (content types and their content).
 * Owns tables: collections, entries.
 */
class CollectionsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'collections');

        $this->commands([
            CreateDefaultPagesCollectionCommand::class,
            CreateHomepageEntryCommand::class,
            PublishScheduledEntriesCommand::class,
        ]);

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('collections.')
            ->group(__DIR__.'/routes.php');
    }
}
