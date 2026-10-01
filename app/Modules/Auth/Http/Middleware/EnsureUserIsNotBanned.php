<?php

namespace App\Modules\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs out and blocks a user the instant they're banned, even mid-session
 * — banning at the login screen (see Fortify::authenticateUsing() in
 * AuthServiceProvider) only stops a FUTURE login attempt, it doesn't
 * revoke a session already in progress. Registered globally on the 'web'
 * middleware group so it applies everywhere, not just the admin panel.
 */
class EnsureUserIsNotBanned
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isBanned()) {
            auth()->guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => __('This account has been banned.')]);
        }

        return $next($request);
    }
}
