<?php

namespace App\Modules\Users\Livewire;

use App\Modules\Users\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Roles')]
class RoleManager extends Component
{
    public function mount(): void
    {
        auth()->user()->authorizePermission('edit roles');
    }

    #[Computed]
    public function roles()
    {
        return Role::withCount(['users', 'userGroups'])->orderBy('title')->get();
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission('edit roles');

        $role = Role::withCount(['users', 'userGroups'])->findOrFail($id);

        if ($role->users_count > 0 || $role->user_groups_count > 0) {
            $parts = array_filter([
                $role->users_count > 0 ? trans_choice(':count user|:count users', $role->users_count, ['count' => $role->users_count]) : null,
                $role->user_groups_count > 0 ? trans_choice(':count user group|:count user groups', $role->user_groups_count, ['count' => $role->user_groups_count]) : null,
            ]);

            Flux::toast(
                variant: 'danger',
                text: __('Cannot delete ":title" — it\'s still assigned to :assignees. Unassign it first.', [
                    'title' => $role->title,
                    'assignees' => implode(' and ', $parts),
                ]),
            );

            return;
        }

        $role->delete();

        Flux::toast(variant: 'success', text: __('Role deleted.'));

        unset($this->roles);
    }

    public function render(): View
    {
        return view('users::livewire.role-manager');
    }
}
