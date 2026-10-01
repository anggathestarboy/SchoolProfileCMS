<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Modules\ActivityLog\Concerns\LogsActivity;
use App\Modules\Auth\Notifications\QueuedResetPassword;
use App\Modules\Sites\Models\Site;
use App\Modules\Users\Models\Role;
use App\Modules\Users\Models\UserGroup;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property string $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $banned_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'banned_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isBanned(): bool
    {
        return $this->banned_at !== null;
    }

    /**
     * Not mass-assignable (see #[Fillable] above) — banning/unbanning is a
     * privileged action gated by its own permission, not something any
     * caller with a plain update() should be able to trigger.
     */
    public function ban(): void
    {
        $this->forceFill(['banned_at' => now()])->save();
    }

    public function unban(): void
    {
        $this->forceFill(['banned_at' => null])->save();
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'user_group_user');
    }

    /**
     * All permissions this user has, whether granted directly through a
     * role or inherited through a user group's roles.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        $direct = $this->roles->flatMap(fn (Role $role): array => $role->permissions);
        $viaGroups = $this->userGroups->flatMap(fn (UserGroup $group): array => $group->permissions());

        return $direct->merge($viaGroups)->unique()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->isSuper() || in_array($permission, $this->permissions(), true);
    }

    /**
     * Whether this user may operate on the given site. Single-site installs
     * short-circuit — there is nowhere else to lock down — so everyone gets
     * access. On multi-site installs, site walls are opt-in: if no user
     * anywhere grants "access {handle} site" permissions, everyone passes
     * (site scoping simply isn't enforced, matching how roles are typically
     * provisioned in tests and small installs). Only once at least one
     * "access ... site" permission exists does the wall come up, and then
     * each user must hold "access {handle} site" for every site they touch
     * (supers bypass via hasPermission).
     */
    public function canAccessSite(string $handle): bool
    {
        if (Site::count() <= 1 || $this->isSuper()) {
            return true;
        }

        $siteAccessPermissions = array_filter(
            $this->permissions(),
            fn (string $permission): bool => str_starts_with($permission, 'access ') && str_ends_with($permission, ' site'),
        );

        if ($siteAccessPermissions === []) {
            return true;
        }

        return $this->hasPermission("access {$handle} site");
    }

    /**
     * Aborts the request with a 403 unless this user has the given
     * permission — the standard guard call for Livewire component actions
     * (mount/create/edit/save/delete), since those aren't reachable through
     * route middleware.
     */
    public function authorizePermission(string $permission): void
    {
        abort_unless($this->hasPermission($permission), 403);
    }

    public function isSuper(): bool
    {
        return in_array('super', $this->permissions(), true);
    }

    public function activityLabel(): string
    {
        return "user '{$this->name}'";
    }

    /**
     * Relabels a plain "updated" as "banned"/"unbanned" when that's what
     * actually changed — much more useful in the activity log than a
     * generic "updated user 'X'" for the single field that matters most.
     *
     * @param  array<string, array{from: mixed, to: mixed}>  $changes
     */
    public function activityAction(string $default, array $changes): string
    {
        if (! array_key_exists('banned_at', $changes)) {
            return $default;
        }

        return $changes['banned_at']['to'] === null ? 'unbanned' : 'banned';
    }

    /**
     * Overrides CanResetPassword's default (sends Laravel's stock
     * ResetPassword notification synchronously) with a queued equivalent —
     * see QueuedResetPassword's docblock for why. Used both by the
     * "Forgot password" form (Fortify's own password.email route) and by
     * an admin's "Send password reset" action on the Users list, since both
     * go through Password::sendResetLink(), which calls this method.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }
}
