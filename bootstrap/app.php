<?php

use App\Http\Middleware\ApplyTenantBranding;
use App\Http\Middleware\CorrelationId;
use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSignupAllowed;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;
use Laravel\Sanctum\Http\Middleware\CheckForAnyAbility;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Trust the local reverse proxy (nginx on this host) for X-Forwarded-*
        // so HTTPS detection (HSTS, secure cookies) and the per-IP login throttle
        // see the real client. Only loopback is trusted — forwarded headers from
        // arbitrary clients are NOT honored, so the client IP can't be spoofed.
        // Add an upstream load balancer's IP here if one is introduced.
        $middleware->trustProxies(at: ['127.0.0.1', '::1']);

        $middleware->alias([
            'user.active' => EnsureUserIsActive::class,
            'permission' => EnsurePermission::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'signup.allowed' => EnsureSignupAllowed::class,
            'abilities' => CheckAbilities::class,
            'ability' => CheckForAnyAbility::class,
        ]);

        // Stage 3.1: resolve UI locale (user pref → session → app default) on web.
        $middleware->web(append: [
            SetLocale::class,
        ]);

        // Stage 4a P2b: resolve the tenant from the host before anything else (web + api).
        $middleware->web(prepend: [ResolveTenant::class]);
        $middleware->api(prepend: [ResolveTenant::class]);

        // Stage 4 4b: apply the resolved tenant's white-label branding (web).
        $middleware->web(append: [ApplyTenantBranding::class]);

        // Cross-cutting 1.4: request/correlation id + actor in log context (web + api).
        $middleware->web(append: [CorrelationId::class]);
        $middleware->api(append: [CorrelationId::class]);

        // Cross-cutting 1.1: baseline security headers on every response (web + api).
        $middleware->append(SecurityHeaders::class);

        // Stage 4f.4: the cookie/consent choice is set by JS and read server-side
        // to gate non-essential scripts, so it must NOT be encrypted.
        $middleware->encryptCookies(except: ['cookie_consent']);

        // Telegram posts updates server-to-server — exempt the webhook from CSRF
        // (it is guarded by the secret path segment instead).
        $middleware->validateCsrfTokens(except: ['telegram/webhook/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
