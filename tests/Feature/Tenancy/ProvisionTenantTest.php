<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\ProvisionTenantAction;
use App\Livewire\Admin\ProviderTenantManager;
use App\Models\AppSetting;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProvisionTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_provisioning_creates_a_tenant_with_its_own_roles_and_settings(): void
    {
        $tenant = app(ProvisionTenantAction::class)->provision('PT Contoh', 'contoh', 'contoh.example');

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id, 'slug' => 'contoh', 'status' => 'active']);

        // The new tenant has its OWN roles (stamped with its tenant_id), distinct
        // from the default tenant's.
        $tenantSuperAdmin = Role::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenant->id)->where('code', 'super_admin')->first();
        $this->assertNotNull($tenantSuperAdmin);
        $this->assertTrue($tenantSuperAdmin->permissions()->exists());

        // And its own app settings.
        $this->assertGreaterThan(0, AppSetting::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count());

        // The default tenant's roles are untouched / separate.
        $defaultId = Tenant::where('is_default', true)->value('id');
        $this->assertNotSame($tenant->id, $defaultId);
    }

    private function platformAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class); // default-tenant roles
        $user = User::factory()->create(); // tenant_id = default via DB default
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user->refresh(); // load the DB-default tenant_id (as the session guard would)
    }

    public function test_platform_admin_can_create_a_tenant_via_the_console(): void
    {
        Livewire::actingAs($this->platformAdmin())
            ->test(ProviderTenantManager::class)
            ->call('newTenant')
            ->set('name', 'Klien Baru')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('tenants', ['name' => 'Klien Baru']);
    }

    public function test_provider_console_route_is_gated_to_platform_admins(): void
    {
        $admin = $this->platformAdmin();
        $requester = User::factory()->create();
        $requester->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        $this->actingAs($requester)->get(route('admin.tenants.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.tenants.index'))->assertOk();
    }
}
