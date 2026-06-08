<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Stage 4 (4c) — self-service onboarding: provision a new tenant (its own RBAC +
 * settings via ProvisionTenantAction) and create its first owner (super-admin of
 * that tenant). Returns the owner; the caller logs them in.
 */
class OnboardTenantAction
{
    public function __construct(private readonly ProvisionTenantAction $provisioner) {}

    /**
     * @param  array{name: string, slug?: ?string, primary_domain?: ?string}  $org
     * @param  array{name: string, email: string, password: string}  $owner
     */
    public function onboard(array $org, array $owner): User
    {
        return DB::transaction(function () use ($org, $owner): User {
            $tenant = $this->provisioner->provision(
                $org['name'],
                $org['slug'] ?? null,
                $org['primary_domain'] ?? null,
            );

            $previousFlag = config('tenancy.enabled');
            config(['tenancy.enabled' => true]); // stamp the owner into the new tenant

            try {
                return app(TenantContext::class)->runFor($tenant->id, function () use ($owner): User {
                    $user = User::create([
                        'name' => $owner['name'],
                        'email' => $owner['email'],
                        'password' => $owner['password'],
                        'is_active' => true,
                    ]);
                    $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

                    return $user->refresh();
                });
            } finally {
                config(['tenancy.enabled' => $previousFlag]);
            }
        });
    }
}
