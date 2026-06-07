<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Booking;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Resource;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 4a P0 spike — proves row-level isolation works on one vertical
 * (resources + bookings) AND that the cross-tenant harness detects a leak.
 */
class TenancySpikeTest extends TestCase
{
    use RefreshDatabase;

    private function actAsTenant(Tenant $tenant): void
    {
        app(TenantContext::class)->set($tenant->id);
    }

    public function test_tenancy_off_is_a_complete_noop(): void
    {
        config(['tenancy.enabled' => false]);

        // No context, scope off → behaves like single-tenant; tenant_id stays null.
        $resource = Resource::factory()->create();

        $this->assertNull($resource->tenant_id);
        $this->assertSame(1, Resource::count());
    }

    public function test_new_rows_are_stamped_with_the_current_tenant(): void
    {
        config(['tenancy.enabled' => true]);
        $t1 = Tenant::factory()->create();
        $this->actAsTenant($t1);

        $resource = Resource::factory()->create();
        $booking = Booking::factory()->approved()->create();

        $this->assertSame($t1->id, $resource->tenant_id);
        $this->assertSame($t1->id, $booking->tenant_id);
    }

    public function test_reads_are_isolated_to_the_current_tenant(): void
    {
        config(['tenancy.enabled' => true]);
        $t1 = Tenant::factory()->create();
        $t2 = Tenant::factory()->create();

        $this->actAsTenant($t1);
        $r1 = Resource::factory()->create();
        Booking::factory()->approved()->create();

        $this->actAsTenant($t2);
        $r2 = Resource::factory()->create();

        // As t2: only t2's rows are visible.
        $this->assertSame(1, Resource::count());
        $this->assertSame(0, Booking::count()); // t2 created no bookings
        $this->assertNull(Resource::find($r1->id));           // cannot see t1's resource
        $this->assertNotNull(Resource::find($r2->id));

        // As t1: sees only t1's.
        $this->actAsTenant($t1);
        $this->assertNull(Resource::find($r2->id));
        $this->assertNotNull(Resource::find($r1->id));
        $this->assertSame(1, Booking::count());
    }

    public function test_harness_detects_a_leak_via_unscoped_query(): void
    {
        config(['tenancy.enabled' => true]);
        $t1 = Tenant::factory()->create();
        $t2 = Tenant::factory()->create();

        $this->actAsTenant($t1);
        Resource::factory()->create();
        $this->actAsTenant($t2);
        Resource::factory()->create();

        // Scoped (correct) sees 1; bypassing the scope (a leak / missing trait)
        // sees BOTH — which the cross-tenant harness asserts against, so any model
        // lacking the scope would be caught here.
        $this->assertSame(1, Resource::count());
        $this->assertSame(2, Resource::withoutGlobalScope('tenant')->count());
    }

    public function test_spike_vertical_models_use_the_tenant_trait(): void
    {
        foreach ([Resource::class, Booking::class] as $model) {
            $this->assertContains(
                BelongsToTenant::class,
                class_uses_recursive($model),
                $model.' must use BelongsToTenant',
            );
        }
    }
}
