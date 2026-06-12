<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-cutting hardening (Part 1.1) — baseline security response headers.
 *
 * The CSP is enforcing by default ({@see config('security.csp_enforce')}).
 * `script-src`/`style-src` retain 'unsafe-inline'/'unsafe-eval' because
 * Livewire/Alpine/Vite require them — locking those would break the app — but
 * the policy still enforces frame-ancestors (clickjacking), base-uri (base-tag
 * injection), form-action (form hijack), connect-src (exfil channels) and
 * object-src 'none' (plugin vectors). Tightening script-src to nonces is future
 * work. HSTS is only emitted over HTTPS so local http development is unaffected.
 */
class SecurityHeaders
{
    /**
     * Enforced CSP. Permissive on script/style (Livewire/Alpine/Vite reality),
     * restrictive everywhere else.
     */
    private const CSP =
        "default-src 'self'; ".
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
        "style-src 'self' 'unsafe-inline'; ".
        "img-src 'self' data:; ".
        "font-src 'self' data:; ".
        "connect-src 'self'; ".
        "frame-ancestors 'self'; ".
        "base-uri 'self'; ".
        "form-action 'self'; ".
        "object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $cspHeader = config('security.csp_enforce', true)
            ? 'Content-Security-Policy'
            : 'Content-Security-Policy-Report-Only';

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
            $cspHeader => self::CSP,
        ];

        // HSTS only over a secure connection (avoid pinning local http to https).
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
