<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-cutting hardening (Part 1.1) — baseline security response headers.
 *
 * The CSP is sent **Report-Only** on purpose: a strict enforcing CSP breaks
 * Livewire/Alpine/Vite (inline + eval). We collect reports, tune, then promote
 * to enforcing — never enforce blind. HSTS is only emitted over HTTPS so local
 * http development is unaffected.
 */
class SecurityHeaders
{
    /**
     * Report-Only CSP starter. Permissive enough for Livewire/Alpine/Vite today;
     * tighten over time using the collected reports before switching to an
     * enforcing `Content-Security-Policy` header.
     */
    private const CSP_REPORT_ONLY =
        "default-src 'self'; ".
        "script-src 'self' 'unsafe-inline' 'unsafe-eval'; ".
        "style-src 'self' 'unsafe-inline'; ".
        "img-src 'self' data:; ".
        "font-src 'self' data:; ".
        "connect-src 'self'; ".
        "frame-ancestors 'self'; ".
        "base-uri 'self'; ".
        "form-action 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), interest-cohort=()',
            'Content-Security-Policy-Report-Only' => self::CSP_REPORT_ONLY,
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
