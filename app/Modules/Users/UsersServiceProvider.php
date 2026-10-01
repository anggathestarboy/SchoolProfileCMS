<?php

namespace App\Modules\Users;

use App\Modules\Users\Console\Commands\CreateSuperAdminCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Control panel users, user groups, and roles/permissions.
 * Owns tables: roles, user_groups, role_user, role_user_group, user_group_user.
 */
class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'users');

        $this->commands([
            CreateSuperAdminCommand::class,
        ]);

        Route::middleware(['web', 'auth', 'verified', 'permission:access cp'])
            ->prefix(config('cms.route'))
            ->name('users.')
            ->group(__DIR__.'/routes.php');
    }
}
