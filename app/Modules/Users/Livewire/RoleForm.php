<?php

namespace App\Modules\Users\Livewire;

use App\Modules\Users\Models\Role;
use App\Modules\Users\Services\PermissionRegistry;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit role')]
class RoleForm extends Component
{
    public ?int $editingId = null;

    public string $title = '';

    public string $handle = '';

    /**
     * @var array<int, string>
     */
    public array $permissions = [];

    /**
     * Full access, bypassing every other permission check (see
     * User::isSuper()) — stored as the 'super' permission, but shown as its
     * own toggle rather than a tree checkbox since checking it makes every
     * other permission moot.
     */
    public bool $superAdmin = false;

    public function mount(?Role $role = null): void
    {
        auth()->user()->authorizePermission('edit roles');

        if (! $role) {
            return;
        }

        $this->editingId = $role->id;
        $this->title = $role->title;
        $this->handle = $role->handle;
        $this->superAdmin = in_array('super', $role->permissions, true);
        $this->permissions = array_values(array_diff($role->permissions, ['super']));
    }

    /**
     * Turning on Super Admin makes every other permission redundant — clear
     * them so the tree (hidden while this is on) doesn't silently keep
     * stale checks that never actually get evaluated.
     */
    public function updatedSuperAdmin(bool $value): void
    {
        if ($value) {
            $this->permissions = [];
        }
    }

    #[Computed]
    public function permissionGroups()
    {
        return app(PermissionRegistry::class)->groups();
    }

    public function regenerateHandle(): void
    {
        $this->handle = Str::slug($this->title, '_');
    }

    /**
     * @param  array<int, string>  $values
     */
    public function checkAll(array $values): void
    {
        $this->permissions = array_values(array_unique([...$this->permissions, ...$values]));
    }

    /**
     * @param  array<int, string>  $values
     */
    public function uncheckAll(array $values): void
    {
        $this->permissions = array_values(array_diff($this->permissions, $values));
    }

    public function save(): void
    {
        auth()->user()->authorizePermission('edit roles');

        // Prune values that no longer exist in the permission registry (a
        // permission removed since the role was granted, or a stale tree
        // posting it) before validating — Rule::in() would otherwise reject
        // the whole save. The registry has no 'super' entry (it's toggled
        // separately and written back below), so a Super Admin role posts an
        // empty tree and the stored permissions become ['super'] only at
        // persist time.
        $this->permissions = $this->superAdmin
            ? []
            : array_values(array_intersect($this->permissions, app(PermissionRegistry::class)->values()));

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255', Rule::unique('roles', 'handle')->ignore($this->editingId)],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(app(PermissionRegistry::class)->values())],
        ]);

        $validated['permissions'] = $this->superAdmin ? ['super'] : $validated['permissions'];

        Role::updateOrCreate(['id' => $this->editingId], $validated);

        Flux::toast(variant: 'success', text: __('Role saved.'));

        $this->redirect(route('users.roles.index'), navigate: true);
    }

    public function render(): View
    {
        return view('users::livewire.role-form');
    }
}
