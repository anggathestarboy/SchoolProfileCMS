<?php

namespace App\Modules\Users\Console\Commands;

use App\Models\User;
use App\Modules\Users\Models\Role;
use Illuminate\Console\Command;

/**
 * Creates the first (or an additional) Super Admin user — one building
 * block of `draft:setup` (see app/Console/Commands/DraftSetupCommand.php),
 * but also runnable on its own.
 */
class CreateSuperAdminCommand extends Command
{
    const SUPER_ADMIN_ROLE_HANDLE = 'super_admin';

    protected $signature = 'users:create-super-admin
        {--name= : Admin name}
        {--email= : Admin email}
        {--password= : Admin password (min 8 characters)}
        {--force : Create another admin even if users already exist}';

    protected $description = 'Create a Super Admin user';

    public function handle(): int
    {
        if (User::query()->exists() && ! $this->option('force')) {
            $this->error('Users already exist. Run `php artisan migrate:fresh` first to start over, or pass --force to add another admin anyway.');

            return self::FAILURE;
        }

        [$name, $email, $password] = $this->promptForAdmin();

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);

        // Not mass-assignable (see User's #[Fillable] attribute) — a fresh
        // CLI-created admin has no email to click a verification link from,
        // so it's pre-verified here or the 'verified' route middleware
        // would lock them out immediately.
        $user->forceFill(['email_verified_at' => now()])->save();

        $role = Role::firstOrCreate(
            ['handle' => self::SUPER_ADMIN_ROLE_HANDLE],
            ['title' => 'Super Admin', 'permissions' => ['super']]
        );
        $user->roles()->attach($role);

        $this->info("Super Admin user created: {$email}");

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    protected function promptForAdmin(): array
    {
        $name = '';

        while ($name === '') {
            $name = trim((string) ($this->option('name') ?: $this->ask('Admin name')));
            $this->forgetOption('name');
        }

        $email = null;

        while (! $email) {
            $candidate = trim((string) ($this->option('email') ?: $this->ask('Admin email')));
            $this->forgetOption('email');

            if (! filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
                $this->error('Enter a valid email address.');

                continue;
            }

            if (User::where('email', $candidate)->exists()) {
                $this->error('That email is already in use.');

                continue;
            }

            $email = $candidate;
        }

        $password = null;

        while (! $password) {
            $candidate = (string) ($this->option('password') ?: $this->secret('Admin password (min 8 characters)'));
            $this->forgetOption('password');

            if (strlen($candidate) < 8) {
                $this->error('Password must be at least 8 characters.');

                continue;
            }

            $password = $candidate;
        }

        return [$name, $email, $password];
    }

    /**
     * A CLI option is only meant to seed the FIRST prompt attempt — once
     * it's been rejected (or used), later loop iterations must fall back
     * to actually asking, not silently reuse the same invalid value forever.
     */
    protected function forgetOption(string $name): void
    {
        $this->input->setOption($name, null);
    }
}
