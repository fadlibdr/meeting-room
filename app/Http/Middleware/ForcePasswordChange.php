<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Redirects a user flagged must_change_password to the forced password-change
 * page until they set a new one. The change page itself and logout are exempt.
 */
class ForcePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User
            && $user->must_change_password
            && ! $request->routeIs('password.change-required')
            && ! $request->routeIs('logout')
            && ! $request->routeIs('locale.update')) {
            return redirect()->route('password.change-required');
        }

        return $next($request);
    }
}
