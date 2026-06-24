<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Two-factor authentication gate (SOC 2 CC6.1 / ISO 27001 A.8.5):
 *  - A session flagged `2fa.pending` (password verified, code not yet) is
 *    confined to the challenge page.
 *  - A user the policy requires to enrol (security.mfa_enforced* via
 *    User::requiresTwoFactor()) is confined to the setup page until enrolled.
 * Logout and the locale switch are always allowed so users aren't trapped.
 */
class TwoFactorGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User
            || $request->routeIs('logout')
            || $request->routeIs('locale.update')) {
            return $next($request);
        }

        if ($request->session()->get('2fa.pending')) {
            return $request->routeIs('two-factor.challenge')
                ? $next($request)
                : redirect()->route('two-factor.challenge');
        }

        if ($user->requiresTwoFactor()) {
            return $request->routeIs('two-factor.setup')
                ? $next($request)
                : redirect()->route('two-factor.setup');
        }

        return $next($request);
    }
}
