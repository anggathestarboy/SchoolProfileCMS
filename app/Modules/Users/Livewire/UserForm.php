<?php

namespace App\Modules\Users\Livewire;

use App\Models\User;
use App\Modules\ActivityLog\Services\ActivityLogger;
use App\Modules\Users\Models\Role;
use App\Modules\Users\Models\UserGroup;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Edit user')]
class UserForm extends Component
{
    public ?string $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    /**
     * Only meaningful while creating a new user (never shown when editing):
     * 'admin' sets the password typed into $password directly, 'reset_link'
     * gives the account an unusable random password and emails the new user
     * a "set your password" link instead — the same reset flow triggered by
     * UserManager::sendPasswordReset() / "Forgot password".
     */
    public string $passwordMode = 'admin';

    /**
     * @var array<int, string>
     */
    public array $roleHandles = [];

    /**
     * @var array<int, string>
     */
    public array $groupHandles = [];

    public function mount(?User $user = null): void
    {
        auth()->user()->authorizePermission($user ? 'edit users' : 'create users');

        if (! $user) {
            return;
        }

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->roleHandles = $user->roles->pluck('handle')->all();
        $this->groupHandles = $user->userGroups->pluck('handle')->all();
    }

    #[Computed]
    public function availableRoles()
    {
        return Role::orderBy('title')->get();
    }

    #[Computed]
    public function availableGroups()
    {
        return UserGroup::orderBy('title')->get();
    }

    public function save(): void
    {
        auth()->user()->authorizePermission($this->editingId ? 'edit users' : 'create users');

        $newUserSetsOwnPassword = ! $this->editingId && $this->passwordMode === 'reset_link';

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'password' => [$this->editingId || $newUserSetsOwnPassword ? 'nullable' : 'required', 'string', 'min:8'],
            'roleHandles' => ['array'],
            'roleHandles.*' => ['string', Rule::exists('roles', 'handle')],
            'groupHandles' => ['array'],
            'groupHandles.*' => ['string', Rule::exists('user_groups', 'handle')],
        ]);

        // Editing an existing user's password/roles/groups is more sensitive
        // than editing their name/email, so those are gated by their own,
        // more specific permissions on top of the base "edit users". A brand
        // new user's initial password/roles/groups are part of "create
        // users" itself — no extra check needed there.
        if ($this->editingId) {
            $existing = User::findOrFail($this->editingId);

            if ($validated['password'] !== '' && $validated['password'] !== null) {
                auth()->user()->authorizePermission('change passwords');
            }

            if (collect($validated['roleHandles'])->sort()->values()->all() !== $existing->roles->pluck('handle')->sort()->values()->all()) {
                auth()->user()->authorizePermission('assign user roles');
            }

            if (collect($validated['groupHandles'])->sort()->values()->all() !== $existing->userGroups->pluck('handle')->sort()->values()->all()) {
                auth()->user()->authorizePermission('assign user groups');
            }
        }

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
        ];

        if ($newUserSetsOwnPassword) {
            // Unusable until they go through the reset link below — nobody
            // ever needs to type or know this value.
            $attributes['password'] = Hash::make(Str::random(40));
        } elseif ($validated['password'] !== '' && $validated['password'] !== null) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        $user = User::updateOrCreate(['id' => $this->editingId], $attributes);

        if ($newUserSetsOwnPassword) {
            Password::sendResetLink(['email' => $user->email]);
        }

        // Pivot syncs don't fire model save events, so User's own
        // LogsActivity trait can't see these — logged explicitly instead.
        $rolesBefore = $user->roles->pluck('handle')->sort()->values()->all();
        $groupsBefore = $user->userGroups->pluck('handle')->sort()->values()->all();

        $user->roles()->sync(Role::whereIn('handle', $validated['roleHandles'])->pluck('id'));
        $user->userGroups()->sync(UserGroup::whereIn('handle', $validated['groupHandles'])->pluck('id'));

        $rolesAfter = collect($validated['roleHandles'])->sort()->values()->all();
        $groupsAfter = collect($validated['groupHandles'])->sort()->values()->all();
        $actor = auth()->user()?->name ?? 'System';

        if ($rolesBefore !== $rolesAfter) {
            ActivityLogger::log(
                action: 'roles_changed',
                subject: $user,
                description: "{$actor} changed roles for user '{$user->name}'",
                changes: ['roles' => ['from' => $rolesBefore, 'to' => $rolesAfter]],
            );
        }

        if ($groupsBefore !== $groupsAfter) {
            ActivityLogger::log(
                action: 'groups_changed',
                subject: $user,
                description: "{$actor} changed groups for user '{$user->name}'",
                changes: ['groups' => ['from' => $groupsBefore, 'to' => $groupsAfter]],
            );
        }

        Flux::toast(
            variant: 'success',
            text: $newUserSetsOwnPassword ? __('User created. A password-reset email was sent to :email.', ['email' => $user->email]) : __('User saved.'),
        );

        $this->redirect(route('users.index'), navigate: true);
    }

    public function render(): View
    {
        return view('users::livewire.user-form');
    }
}
