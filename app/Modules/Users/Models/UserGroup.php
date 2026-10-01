<?php

namespace App\Modules\Users\Models;

use App\Models\User;
use App\Modules\ActivityLog\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $handle
 * @property string $title
 */
class UserGroup extends Model
{
    use LogsActivity;

    protected $table = 'user_groups';

    protected $fillable = ['handle', 'title'];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_group_user');
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user_group');
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return $this->roles->flatMap(fn (Role $role): array => $role->permissions)->unique()->values()->all();
    }

    public function activityLabel(): string
    {
        return "user group '{$this->title}'";
    }
}
