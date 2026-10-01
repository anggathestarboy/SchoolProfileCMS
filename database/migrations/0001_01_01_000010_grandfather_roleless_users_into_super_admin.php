<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Permission checks were previously defined (PermissionRegistry, Role/User::
 * hasPermission()) but never actually enforced anywhere — every
 * authenticated, verified user had full access regardless of role. Now that
 * enforcement is wired in, a user with zero roles would suddenly be locked
 * out of the whole control panel. This backfills a "Super Admin" role (the
 * 'super' permission, which bypasses every check) onto every such user, so
 * existing installs keep the access they already had; only users who were
 * already assigned a deliberately-restricted role (e.g. "editor") start
 * being genuinely restricted, which is the intended behavior change.
 */
return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('handle', 'super_admin')->value('id');

        if (! $roleId) {
            $roleId = DB::table('roles')->insertGetId([
                'handle' => 'super_admin',
                'title' => 'Super Admin',
                'permissions' => json_encode(['super']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $roleless = DB::table('users')
            ->whereNotIn('id', DB::table('role_user')->select('user_id'))
            ->pluck('id');

        foreach ($roleless as $userId) {
            DB::table('role_user')->insert(['role_id' => $roleId, 'user_id' => $userId]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('handle', 'super_admin')->value('id');

        if ($roleId) {
            DB::table('role_user')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }
    }
};
