<?php

use App\Http\Middleware\EnsurePermission;
use App\Modules\Auth\Http\Middleware\EnsureUserIsNotBanned;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['permission' => EnsurePermission::class]);
        $middleware->web(append: [EnsureUserIsNotBanned::class]);

        // Trust the reverse proxy in front of this app (e.g. the Docker
        // staging setup's host proxy/Cloudflare terminating HTTPS and
        // forwarding plain HTTP to the container) so Laravel reads the
        // client's real scheme/host/IP from X-Forwarded-* headers instead
        // of the proxy's own plain-HTTP connection — otherwise every
        // generated URL (including Livewire's own update/asset endpoints)
        // comes out as http://, which browsers block as mixed content on
        // an https:// page. '*' trusts any proxy IP, which is fine here
        // since the app is never reachable except through one.
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
