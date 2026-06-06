<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 3.1 — resolves the active UI locale for the request.
 *
 * Precedence: authenticated user's saved locale → guest session locale → the
 * application default (config app.locale). Only locales declared in
 * config('app.available_locales') are honoured; anything else falls through to
 * the default, so a stale/unknown value can never blank the UI.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->resolve($request);

        if ($locale !== null) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    private function resolve(Request $request): ?string
    {
        /** @var array<string, string> $available */
        $available = config('app.available_locales', []);
        $codes = array_keys($available);

        $user = $request->user();
        if ($user instanceof User && $user->locale !== null && in_array($user->locale, $codes, true)) {
            return $user->locale;
        }

        if ($request->hasSession()) {
            $session = $request->session()->get('locale');
            if (is_string($session) && in_array($session, $codes, true)) {
                return $session;
            }
        }

        return null;
    }
}
