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
 * @property array<int, string> $permissions
 */
class Role extends Model
{
    use LogsActivity;

    protected $table = 'roles';

    protected $fillable = ['handle', 'title', 'permissions'];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'handle';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'role_user');
    }

    public function userGroups(): BelongsToMany
    {
        return $this->belongsToMany(UserGroup::class, 'role_user_group');
    }

    public function hasPermission(string $permission): bool
    {
        return in_array('super', $this->permissions, true) || in_array($permission, $this->permissions, true);
    }

    public function activityLabel(): string
    {
        return "role '{$this->title}'";
    }
}
