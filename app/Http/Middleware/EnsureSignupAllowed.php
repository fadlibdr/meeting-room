<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 4 (4c) — self-service tenant sign-up is opt-in (config tenancy.allow_signup).
 */
class EnsureSignupAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless((bool) config('tenancy.allow_signup'), 404);

        return $next($request);
    }
}
