<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cross-cutting 1.4 — request/correlation id + actor context for tracing.
 *
 * Honours an inbound `X-Request-Id` (so a correlation id can flow across
 * services) or mints one. The id + the authenticated actor are pushed into
 * Laravel's Context, which is automatically attached to every log entry made
 * during the request, and echoed back on the response for client-side
 * correlation.
 */
class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id');
        if (! is_string($requestId) || trim($requestId) === '') {
            $requestId = (string) Str::uuid();
        }

        Context::add('request_id', $requestId);

        $user = $request->user();
        if ($user !== null) {
            Context::add('actor_id', $user->getAuthIdentifier());
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
