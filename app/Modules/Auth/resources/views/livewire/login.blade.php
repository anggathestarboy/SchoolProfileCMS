<x-layouts::auth :title="__('Log in')">
    <div class="flex flex-col gap-6">
        <x-auth-header :title="__('Log in to your account')" :description="__('Enter your email and password below to log in')" />

        <!-- Session Status -->
        <x-auth-session-status class="text-center" :status="session('status')" />

        <x-passkey-verify />

        @php $recaptcha = app(\App\Services\RecaptchaService::class); @endphp

        <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-6" id="login-form">
            @csrf

            @if ($recaptcha->enabled())
                <x-recaptcha-script />
                <input type="hidden" name="recaptcha_token" id="recaptcha_token">
            @endif

            <!-- Email Address -->
            <flux:input
                name="email"
                :label="__('Email address')"
                :value="old('email')"
                type="email"
                required
                autofocus
                autocomplete="email"
                placeholder="email@example.com"
            />

            <!-- Password -->
            <div class="relative">
                <flux:input
                    name="password"
                    :label="__('Password')"
                    type="password"
                    required
                    autocomplete="current-password"
                    :placeholder="__('Password')"
                    viewable
                />

                @if (Route::has('password.request'))
                    <flux:link class="absolute top-0 text-sm end-0" :href="route('password.request')" wire:navigate>
                        {{ __('Forgot your password?') }}
                    </flux:link>
                @endif
            </div>

            <!-- Remember Me -->
            <flux:checkbox name="remember" :label="__('Remember me')" :checked="old('remember')" />

            <div class="flex items-center justify-end">
                <flux:button variant="primary" type="submit" class="w-full" data-test="login-button">
                    {{ __('Log in') }}
                </flux:button>
            </div>
        </form>

        @if ($recaptcha->enabled())
            <script>
                (function () {
                    const form = document.getElementById('login-form');

                    // Guards against this <script> block executing more than
                    // once for the same form element (e.g. Livewire
                    // re-running inline scripts during its own hydration
                    // pass) — without this, two 'submit' listeners end up
                    // attached, each grabbing its own token on every click,
                    // and Google rejects one of the two as
                    // "timeout-or-duplicate" every single time.
                    if (!form || form.dataset.recaptchaBound === '1') {
                        return;
                    }
                    form.dataset.recaptchaBound = '1';

                    form.addEventListener('submit', function (event) {
                        // form.submit() below does NOT re-fire the 'submit'
                        // event (unlike a real click), so this only ever
                        // runs once per actual user click.
                        event.preventDefault();

                        const submitButton = form.querySelector('[data-test="login-button"]');
                        submitButton.disabled = true;

                        grecaptcha.ready(function () {
                            grecaptcha.execute('{{ $recaptcha->siteKey() }}', { action: 'login' }).then(function (token) {
                                document.getElementById('recaptcha_token').value = token;
                                form.submit();
                            }).catch(function () {
                                // Google's script itself failed (blocked, offline, etc.) — let
                                // the request through with an empty token rather than trap the
                                // user on a dead submit button; the server-side check below
                                // rejects an empty/invalid token the same as a failed score.
                                submitButton.disabled = false;
                                form.submit();
                            });
                        });
                    });
                })();
            </script>
        @endif
    </div>
</x-layouts::auth>
