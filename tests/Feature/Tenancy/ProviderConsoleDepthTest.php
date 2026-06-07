<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Livewire\Admin\ProviderTenantManager;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProviderConsoleDepthTest extends TestCase
{
    use RefreshDatabase;

    private function platformAdmin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', 'super_admin')->firstOrFail()->id]);

        return $user->refresh();
    }

    public function test_console_shows_per_tenant_usage_counts(): void
    {
        config(['tenancy.enabled' => true]);
        $t2 = Tenant::factory()->create(['name' => 'Tenant Dua']);
        app(TenantContext::class)->runFor($t2->id, function () {
            User::factory()->count(2)->create();
            Booking::factory()->approved()->create();
        });

        Livewire::actingAs($this->platformAdmin())
            ->test(ProviderTenantManager::class)
            ->assertSee('Tenant Dua')
            ->assertSeeInOrder(['Tenant Dua', '2']); // 2 users under t2
    }

    public function test_platform_admin_can_toggle_tenant_feature_flags(): void
    {
        $admin = $this->platformAdmin();
        $tenant = Tenant::factory()->create();

        Livewire::actingAs($admin)
            ->test(ProviderTenantManager::class)
            ->call('editFeatures', $tenant->id)
            ->set('featureFlags.webhooks', true)
            ->set('featureFlags.public_api', false)
            ->call('saveFeatures')
            ->assertHasNoErrors();

        $fresh = $tenant->fresh();
        $this->assertTrue($fresh->feature('webhooks'));
        $this->assertFalse($fresh->feature('public_api'));
        // Only known flags are persisted.
        $this->assertSame(['calendar_sync', 'webhooks', 'exports', 'public_api'], array_keys($fresh->features));
    }
}
