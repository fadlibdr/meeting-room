<?php

declare(strict_types=1);

namespace Tests\Feature\Demo;

use App\Actions\SeedDemoTenantAction;
use App\Models\Booking;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSandboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_try_route_404s_while_demo_disabled(): void
    {
        config(['demo.enabled' => false]);
        $this->get(route('demo.try'))->assertNotFound();
    }

    public function test_reset_command_is_a_noop_while_disabled(): void
    {
        config(['demo.enabled' => false]);

        $this->artisan('demo:reset')->expectsOutputToContain('Demo disabled')->assertSuccessful();

        $this->assertDatabaseMissing('tenants', ['slug' => 'demo']);
    }

    public function test_seed_provisions_demo_tenant_with_sample_data(): void
    {
        config(['demo.enabled' => true, 'demo.sample_resources' => 4]);

        $tenant = app(SeedDemoTenantAction::class)->seed();

        $this->assertSame('demo', $tenant->slug);

        // Sample data lands inside the demo tenant.
        app(TenantContext::class)->runFor($tenant->id, function (): void {
            config(['tenancy.enabled' => true]);
            $this->assertSame(4, Resource::query()->count());
            $this->assertGreaterThan(0, Booking::query()->count());
        });
        app(TenantContext::class)->forget();
        config(['tenancy.enabled' => false]);

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'email' => 'demo@demo.invalid',
            'is_active' => true,
        ]);
    }

    public function test_reset_is_idempotent_and_does_not_multiply_resources(): void
    {
        config(['demo.enabled' => true, 'demo.sample_resources' => 4]);

        $action = app(SeedDemoTenantAction::class);
        $tenant = $action->seed();
        $action->seed(); // second reset

        app(TenantContext::class)->runFor($tenant->id, function (): void {
            config(['tenancy.enabled' => true]);
            $this->assertSame(4, Resource::query()->count()); // not 8
        });
        app(TenantContext::class)->forget();
        config(['tenancy.enabled' => false]);

        // Only one demo tenant + one demo user, regardless of reset count.
        $this->assertSame(1, Tenant::query()->where('slug', 'demo')->count());
        $this->assertSame(1, User::withoutGlobalScope('tenant')->where('email', 'demo@demo.invalid')->count());
    }

    public function test_try_flow_logs_into_the_demo_user_when_enabled(): void
    {
        config(['demo.enabled' => true]);

        $this->get(route('demo.try'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertSame('demo@demo.invalid', auth()->user()->email);
    }

    public function test_demo_data_stays_within_the_demo_tenant(): void
    {
        config(['demo.enabled' => true]);

        $tenant = app(SeedDemoTenantAction::class)->seed();

        // The default (BPJS) tenant must not have gained the demo resources.
        $defaultId = Tenant::query()->where('is_default', true)->value('id');
        $this->assertNotSame($defaultId, $tenant->id);

        $demoResourceCount = Resource::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
        $defaultResourceCount = Resource::withoutGlobalScope('tenant')->where('tenant_id', $defaultId)->count();

        $this->assertGreaterThan(0, $demoResourceCount);
        // Demo seeding never created resources under the default tenant.
        $this->assertSame(0, $defaultResourceCount);
    }
}
