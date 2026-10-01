<?php

namespace App\Modules\Users\Livewire;

use App\Modules\Users\Models\Role;
use App\Modules\Users\Models\UserGroup;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('User Groups')]
class UserGroupManager extends Component
{
    public ?int $editingId = null;

    public string $title = '';

    public string $handle = '';

    /**
     * @var array<int, string>
     */
    public array $roleHandles = [];

    public function mount(): void
    {
        auth()->user()->authorizePermission('edit user groups');
    }

    #[Computed]
    public function groups()
    {
        return UserGroup::with('roles')->withCount('users')->orderBy('title')->get();
    }

    #[Computed]
    public function availableRoles()
    {
        return Role::orderBy('title')->get();
    }

    public function create(): void
    {
        auth()->user()->authorizePermission('edit user groups');

        $this->reset(['editingId', 'title', 'handle', 'roleHandles']);
        Flux::modal('user-group-form')->show();
    }

    public function edit(int $id): void
    {
        auth()->user()->authorizePermission('edit user groups');

        $group = UserGroup::with('roles')->findOrFail($id);

        $this->editingId = $group->id;
        $this->title = $group->title;
        $this->handle = $group->handle;
        $this->roleHandles = $group->roles->pluck('handle')->all();

        Flux::modal('user-group-form')->show();
    }

    public function regenerateHandle(): void
    {
        $this->handle = Str::slug($this->title, '_');
    }

    public function save(): void
    {
        auth()->user()->authorizePermission('edit user groups');

        $validated = $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'handle' => ['required', 'string', 'max:255', Rule::unique('user_groups', 'handle')->ignore($this->editingId)],
            'roleHandles' => ['array'],
            'roleHandles.*' => ['string', Rule::exists('roles', 'handle')],
        ]);

        $group = UserGroup::updateOrCreate(['id' => $this->editingId], [
            'title' => $validated['title'],
            'handle' => $validated['handle'],
        ]);

        $group->roles()->sync(Role::whereIn('handle', $validated['roleHandles'])->pluck('id'));

        Flux::modal('user-group-form')->close();
        Flux::toast(variant: 'success', text: __('User group saved.'));
        unset($this->groups);
    }

    public function delete(int $id): void
    {
        auth()->user()->authorizePermission('edit user groups');

        UserGroup::findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('User group deleted.'));

        unset($this->groups);
    }

    public function render(): View
    {
        return view('users::livewire.user-group-manager');
    }
}
