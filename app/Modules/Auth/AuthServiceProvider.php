<?php

namespace App\Modules\Auth;

use App\Models\User;
use App\Modules\Auth\Actions\ResetUserPassword;
use App\Services\RecaptchaService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Livewire\Livewire;

/**
 * Login, password reset, two-factor challenge, email verification, and
 * password confirmation — everything Laravel Fortify handles — plus the
 * authenticated user's own account settings (profile, security/2FA/
 * passkeys, appearance, account deletion). Owns no tables of its own
 * (Fortify persists onto the existing users table).
 */
class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'auth');

        // Lets @livewire("auth::settings.delete-user-form") and
        // @livewire("auth::settings.two-factor.recovery-codes") calls in
        // this module's own views resolve to this module's own Livewire
        // namespace — same convention as MediaServiceProvider's 'media'
        // namespace for @livewire("media::asset-picker").
        Livewire::addNamespace('auth', classNamespace: 'App\\Modules\\Auth\\Livewire');

        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Default credential check, plus a banned-account block — done here
        // rather than left to EnsureUserIsNotBanned (which only guards
        // requests AFTER a session exists) so a banned user gets a clear
        // "banned" message on the login form itself instead of a generic
        // "these credentials don't match" or being let in and immediately
        // kicked back out.
        Fortify::authenticateUsing(function (Request $request) {
            $recaptcha = app(RecaptchaService::class);

            if ($recaptcha->enabled() && ! $recaptcha->verify($request->input('recaptcha_token'), 'login')) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('We could not verify you are human. Please try again.'),
                ]);
            }

            $user = User::where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password)) {
                return null;
            }

            if ($user->isBanned()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('This account has been banned.'),
                ]);
            }

            return $user;
        });
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn () => view('auth::livewire.login'));
        Fortify::verifyEmailView(fn () => view('auth::livewire.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('auth::livewire.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth::livewire.confirm-password'));
        Fortify::resetPasswordView(fn () => view('auth::livewire.reset-password'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth::livewire.forgot-password'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
