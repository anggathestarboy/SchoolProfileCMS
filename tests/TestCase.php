<?php

namespace Tests;

use App\Models\User;
use App\Modules\Users\Models\Role;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    /**
     * Mirrors the production "grandfather roleless users into Super Admin"
     * migration: a test's `actingAs(User::factory()->create())` predates
     * real permission enforcement and means "any authenticated user", so a
     * freshly created user with no roles yet gets Super Admin here too —
     * existing tests don't need to know permissions exist. A test that
     * deliberately wants a permission-restricted user should attach its own
     * role(s) to the user before calling actingAs(), which skips this.
     */
    public function actingAs(Authenticatable $user, $guard = null): static
    {
        if ($user instanceof User && $user->roles()->count() === 0) {
            $role = Role::firstOrCreate(['handle' => 'super_admin'], ['title' => 'Super Admin', 'permissions' => ['super']]);
            $user->roles()->attach($role);
            $user->unsetRelation('roles');
        }

        return parent::actingAs($user, $guard);
    }
}
