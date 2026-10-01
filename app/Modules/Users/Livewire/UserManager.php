<?php

namespace App\Modules\Users\Livewire;

use App\Models\User;
use App\Modules\ActivityLog\Services\ActivityLogger;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Users')]
class UserManager extends Component
{
    public string $search = '';

    /**
     * Ids checked in the bulk-select column — see bulkDelete()/bulkBan()/
     * bulkUnban().
     *
     * @var array<int, string>
     */
    public array $selected = [];

    public function mount(): void
    {
        auth()->user()->authorizePermission('view users');
    }

    #[Computed]
    public function users()
    {
        return User::with(['roles', 'userGroups'])
            ->when($this->search !== '', function ($query) {
                $query->where(function ($query) {
                    $query->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%");
                });
            })
            ->orderBy('name')
            ->get();
    }

    public function updatedSearch(): void
    {
        $this->selected = [];
    }

    /**
     * Checks/unchecks every currently-listed (search-filtered) user at once.
     */
    public function toggleSelectAll(): void
    {
        $ids = $this->users->pluck('id')->all();

        $this->selected = $ids !== [] && count(array_intersect($this->selected, $ids)) === count($ids)
            ? []
            : $ids;
    }

    public function delete(string $id): void
    {
        auth()->user()->authorizePermission('delete users');

        if ($id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __("You can't delete your own account."));

            return;
        }

        User::findOrFail($id)->delete();

        Flux::toast(variant: 'success', text: __('User deleted.'));

        unset($this->users);
    }

    public function ban(string $id): void
    {
        auth()->user()->authorizePermission('ban users');

        if ($id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __("You can't ban your own account."));

            return;
        }

        User::findOrFail($id)->ban();

        Flux::toast(variant: 'success', text: __('User banned.'));

        unset($this->users);
    }

    public function unban(string $id): void
    {
        auth()->user()->authorizePermission('ban users');

        if ($id === auth()->id()) {
            Flux::toast(variant: 'danger', text: __("You can't unban your own account."));

            return;
        }

        User::findOrFail($id)->unban();

        Flux::toast(variant: 'success', text: __('User unbanned.'));

        unset($this->users);
    }

    /**
     * Emails the user a password reset link — the same broker/notification
     * Fortify's own "Forgot password" form uses, just triggered by an admin
     * instead of the user themselves.
     */
    public function sendPasswordReset(string $id): void
    {
        auth()->user()->authorizePermission('send password reset');

        $user = User::findOrFail($id);
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $actor = auth()->user()?->name ?? 'System';

            ActivityLogger::log(
                action: 'password_reset_sent',
                subject: $user,
                description: "{$actor} sent a password reset link to '{$user->name}'",
            );
        }

        Flux::toast(
            variant: $status === Password::RESET_LINK_SENT ? 'success' : 'danger',
            text: __($status),
        );
    }

    public function bulkDelete(): void
    {
        auth()->user()->authorizePermission('delete users');

        $ids = $this->selectedExcludingSelf();
        $count = count($ids);

        User::whereIn('id', $ids)->delete();

        $this->selected = [];
        unset($this->users);

        Flux::toast(variant: 'success', text: trans_choice(':count user deleted.|:count users deleted.', $count, ['count' => $count]));
    }

    public function bulkBan(): void
    {
        auth()->user()->authorizePermission('ban users');

        $ids = $this->selectedExcludingSelf();
        $count = count($ids);

        User::whereIn('id', $ids)->get()->each->ban();

        $this->selected = [];
        unset($this->users);

        Flux::toast(variant: 'success', text: trans_choice(':count user banned.|:count users banned.', $count, ['count' => $count]));
    }

    public function bulkUnban(): void
    {
        auth()->user()->authorizePermission('ban users');

        $ids = $this->selectedExcludingSelf();
        $count = count($ids);

        User::whereIn('id', $ids)->get()->each->unban();

        $this->selected = [];
        unset($this->users);

        Flux::toast(variant: 'success', text: trans_choice(':count user unbanned.|:count users unbanned.', $count, ['count' => $count]));
    }

    /**
     * The checked ids, minus the acting user's own — bulk-deleting or
     * -banning yourself would lock you out (or worse, delete the account
     * you're using right now) with no easy way back in.
     *
     * @return array<int, string>
     */
    protected function selectedExcludingSelf(): array
    {
        return array_values(array_diff($this->selected, [auth()->id()]));
    }

    public function render(): View
    {
        return view('users::livewire.user-manager');
    }
}
