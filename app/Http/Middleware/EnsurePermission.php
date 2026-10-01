<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a request unless the authenticated user has the given permission
 * (see User::hasPermission(), PermissionRegistry). Registered as the
 * 'permission' route middleware alias — use as 'permission:access cp'.
 */
class EnsurePermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        abort_unless($request->user()?->hasPermission($permission), 403);

        return $next($request);
    }
}
