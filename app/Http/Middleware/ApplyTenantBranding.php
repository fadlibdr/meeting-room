<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stage 4 (4b) — applies the current tenant's white-label branding PER REQUEST
 * (after the tenant is resolved), unlike boot-time config overrides which are
 * single-tenant. Sets the app name + mail-from and shares a `branding` array to
 * views (brand name, colour, logo) with BPJS defaults as the fallback. No-op
 * when tenancy is off or no tenant is resolved.
 */
class ApplyTenantBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        $branding = [
            'name' => (string) config('app.name', 'BPJS Kesehatan'),
            'color' => '#005490', // BPJS blue default
            'logo_url' => null,
        ];

        if (config('tenancy.enabled')) {
            $tenantId = app(TenantContext::class)->id();
            $tenant = $tenantId !== null ? Tenant::find($tenantId) : null;

            if ($tenant instanceof Tenant) {
                if (($tenant->brand_name ?? '') !== '') {
                    $branding['name'] = $tenant->brand_name;
                    config(['app.name' => $tenant->brand_name]);
                }
                if (($tenant->brand_color ?? '') !== '') {
                    $branding['color'] = $tenant->brand_color;
                }
                $branding['logo_url'] = $tenant->logo_url;

                if (($tenant->email_from_address ?? '') !== '') {
                    config(['mail.from.address' => $tenant->email_from_address]);
                }
                if (($tenant->email_from_name ?? '') !== '') {
                    config(['mail.from.name' => $tenant->email_from_name]);
                }
            }
        }

        View::share('branding', $branding);

        return $next($request);
    }
}
