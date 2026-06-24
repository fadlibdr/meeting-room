<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SettingsService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces idle and absolute session timeouts (SOC 2 CC6.1 / ISO 27001 A.8.5),
 * both operator-configurable:
 *   - security.session_idle_timeout_minutes     (0 = disabled)
 *   - security.session_absolute_timeout_minutes  (0 = disabled)
 *
 * On an authenticated web request: logs the user out (invalidating the session)
 * when inactivity exceeds the idle window, or when time since first authenticated
 * request exceeds the absolute window. Otherwise stamps the activity timestamp.
 */
class SessionTimeout
{
    private const LAST_ACTIVITY = '_last_activity_at';

    private const AUTH_STARTED = '_auth_started_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $settings = app(SettingsService::class);
        $idle = max(0, (int) $settings->get('security.session_idle_timeout_minutes', 30)) * 60;
        $absolute = max(0, (int) $settings->get('security.session_absolute_timeout_minutes', 480)) * 60;

        $now = now()->timestamp;
        $session = $request->session();

        $startedAt = (int) $session->get(self::AUTH_STARTED, $now);
        $lastActivity = (int) $session->get(self::LAST_ACTIVITY, $now);

        $idleExpired = $idle > 0 && ($now - $lastActivity) > $idle;
        $absoluteExpired = $absolute > 0 && ($now - $startedAt) > $absolute;

        if ($idleExpired || $absoluteExpired) {
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            return redirect()->route('login')->with(
                'status',
                __('Sesi Anda telah berakhir. Silakan masuk kembali.'),
            );
        }

        if (! $session->has(self::AUTH_STARTED)) {
            $session->put(self::AUTH_STARTED, $now);
        }
        $session->put(self::LAST_ACTIVITY, $now);

        return $next($request);
    }
}
