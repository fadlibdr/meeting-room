<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    /**
     * Usage: ->middleware('permission:bookings.approve')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        // Sprint 1 wires User::hasPermission() to PermissionCacheService.
        if (! $user->hasPermission($permission)) {
            abort(403, 'Anda tidak memiliki izin untuk melakukan tindakan ini.');
        }

        return $next($request);
    }
}
