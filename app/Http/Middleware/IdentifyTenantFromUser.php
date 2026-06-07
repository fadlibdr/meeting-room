<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 4a P2d — for token-authenticated APIs, the tenant is the authenticated
 * user's tenant (not the request host). Runs AFTER auth:sanctum and pins the
 * context to the token owner's tenant, so a token only ever operates within its
 * own tenant regardless of which host it was presented to. No-op when tenancy
 * is off or unauthenticated.
 */
class IdentifyTenantFromUser
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('tenancy.enabled')) {
            $user = $request->user();
            if ($user instanceof User) {
                app(TenantContext::class)->set((int) $user->tenant_id);
            }
        }

        return $next($request);
    }
}
