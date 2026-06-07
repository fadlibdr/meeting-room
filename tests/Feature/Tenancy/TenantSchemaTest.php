<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Resource;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stage 4a P1 — proves the tenant schema: a default tenant, tenant_id columns,
 * and composite (tenant_id, …) uniqueness that allows per-tenant duplicates.
 */
class TenantSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_default_tenant_exists(): void
    {
        $default = Tenant::where('is_default', true)->first();
        $this->assertNotNull($default);
        $this->assertSame('bpjs', $default->slug);
    }

    public function test_tenant_id_columns_are_present(): void
    {
        foreach (['resources', 'bookings', 'users', 'units', 'roles', 'app_settings', 'webhook_subscriptions'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'tenant_id'), "$table.tenant_id missing");
        }
    }

    public function test_codes_may_repeat_across_tenants_but_not_within_one(): void
    {
        $default = Tenant::where('is_default', true)->value('id');
        $other = Tenant::factory()->create()->id;

        // Same code under two different tenants → allowed.
        Resource::factory()->create(['code' => 'DUP-1', 'tenant_id' => $default]);
        Resource::factory()->create(['code' => 'DUP-1', 'tenant_id' => $other]);
        $this->assertSame(2, Resource::withoutGlobalScope('tenant')->where('code', 'DUP-1')->count());

        // Same code within the same tenant → rejected by the composite unique.
        $this->expectException(QueryException::class);
        Resource::factory()->create(['code' => 'DUP-1', 'tenant_id' => $other]);
    }
}
