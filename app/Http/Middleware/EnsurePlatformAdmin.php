<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 4 (4e) — restricts the provider console to platform operators
 * (super-admin of the default tenant). Gates the initial page load; component
 * actions additionally re-check via their own guard.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isPlatformAdmin(), 403);

        return $next($request);
    }
}
