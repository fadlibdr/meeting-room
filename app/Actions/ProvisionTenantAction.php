<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stage 4 (4e/4c) — provision a new tenant: create the tenant row, then seed
 * ITS OWN roles + role→permission assignments and app settings.
 *
 * Tenant stamping is force-enabled during seeding so the seeded rows land in the
 * new tenant regardless of the platform tenancy flag (otherwise the trait would
 * fall back to the DB-default tenant). Permissions are a global catalog and are
 * reused (firstOrCreate); roles/role_permissions/app_settings are per-tenant.
 */
class ProvisionTenantAction
{
    public function provision(string $name, ?string $slug = null, ?string $primaryDomain = null): Tenant
    {
        return DB::transaction(function () use ($name, $slug, $primaryDomain): Tenant {
            $tenant = Tenant::create([
                'name' => $name,
                'slug' => $slug ?: Str::slug($name).'-'.Str::lower(Str::random(4)),
                'primary_domain' => $primaryDomain,
                'status' => 'active',
            ]);

            $previousFlag = config('tenancy.enabled');
            config(['tenancy.enabled' => true]); // force per-tenant stamping during seed

            try {
                app(TenantContext::class)->runFor($tenant->id, function (): void {
                    (new RolesAndPermissionsSeeder)->run();
                    (new AppSettingsSeeder)->run();
                });
            } finally {
                config(['tenancy.enabled' => $previousFlag]);
            }

            return $tenant->refresh();
        });
    }
}
