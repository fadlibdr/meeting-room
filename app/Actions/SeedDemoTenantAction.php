<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Stage 4h.2 — (re)build the demo/sandbox tenant with fresh sample data.
 *
 * Idempotent and self-contained: provisions the demo tenant if missing, then
 * inside its tenant context wipes the prior sample data and re-seeds a small,
 * predictable set. All mutations are scoped to the demo tenant, so a reset can
 * never touch real tenants' data.
 *
 * Callers (the demo:reset command, the /try flow) are responsible for the
 * config('demo.enabled') gate — this action assumes the decision was made.
 */
class SeedDemoTenantAction
{
    public function seed(): Tenant
    {
        $tenant = $this->demoTenant();

        $previousFlag = config('tenancy.enabled');
        config(['tenancy.enabled' => true]); // force per-tenant stamping/scoping

        try {
            app(TenantContext::class)->runFor($tenant->id, function () use ($tenant): void {
                $this->resetSampleData();
                $this->seedResources();
                $this->seedDemoUser($tenant);
                $this->seedSampleBookings();
            });
        } finally {
            config(['tenancy.enabled' => $previousFlag]);
        }

        return $tenant->refresh();
    }

    private function demoTenant(): Tenant
    {
        $slug = (string) config('demo.tenant_slug', 'demo');

        $tenant = Tenant::withoutGlobalScope('tenant')->where('slug', $slug)->first();
        if ($tenant instanceof Tenant) {
            return $tenant;
        }

        return app(ProvisionTenantAction::class)->provision(
            (string) config('demo.tenant_name', 'Demo'),
            $slug,
        );
    }

    private function resetSampleData(): void
    {
        // Scoped to the demo tenant by the active context.
        Booking::query()->delete();
        Resource::query()->delete();
    }

    private function seedResources(): void
    {
        $count = max(1, (int) config('demo.sample_resources', 4));
        Resource::factory()->count($count)->create();
    }

    private function seedDemoUser(Tenant $tenant): void
    {
        $email = (string) config('demo.user_email', 'demo@demo.invalid');

        $user = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)
            ->where('email', $email)
            ->first();

        if (! $user instanceof User) {
            $user = new User;
            $user->email = $email;
        }

        $user->forceFill([
            'tenant_id' => $tenant->id,
            'name' => (string) config('demo.user_name', 'Pengguna Demo'),
            'password' => Hash::make(Str::random(40)), // unusable; entry is via /try only
            'is_active' => true,
            'email_verified_at' => now(),
        ])->save();

        $role = Role::query()->where('code', 'requester')->first();
        if ($role instanceof Role) {
            $user->roles()->sync([$role->id]);
        }
    }

    private function seedSampleBookings(): void
    {
        $resource = Resource::query()->first();
        $user = User::query()->where('email', config('demo.user_email'))->first();

        if ($resource instanceof Resource && $user instanceof User) {
            Booking::factory()->count(2)->create([
                'resource_id' => $resource->id,
                'requester_user_id' => $user->id,
            ]);
        }
    }

    /**
     * The auto-login demo user (used by the /try flow).
     */
    public function demoUser(): ?User
    {
        return User::withoutGlobalScope('tenant')
            ->where('email', config('demo.user_email'))
            ->first();
    }
}
