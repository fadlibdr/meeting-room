<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 4a P2b — resolve the current tenant from the request host and set the
 * TenantContext (so BelongsToTenant scopes apply).
 *
 * Resolution order: exact primary_domain match → first subdomain label as slug
 * → the default tenant (so the existing single host keeps working). No-op while
 * tenancy is disabled. Web-only; console/queue use TenantContext::runFor().
 */
class ResolveTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (config('tenancy.enabled')) {
            $tenant = $this->resolve($request->getHost());
            if ($tenant !== null) {
                app(TenantContext::class)->set($tenant->id);
            }
        }

        return $next($request);
    }

    private function resolve(string $host): ?Tenant
    {
        $byDomain = Tenant::query()->where('primary_domain', $host)->first();
        if ($byDomain !== null) {
            return $byDomain;
        }

        $label = explode('.', $host)[0];
        if ($label !== '') {
            $bySlug = Tenant::query()->where('slug', $label)->first();
            if ($bySlug !== null) {
                return $bySlug;
            }
        }

        return Tenant::query()->where('is_default', true)->first();
    }
}
