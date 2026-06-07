<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Stage 4a P2e — the cross-tenant leak gate. With tenancy ON, a request scoped
 * to one tenant (by host) must never see another tenant's data, across the
 * read API + direct id access (IDOR).
 */
class CrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $ctx;

    private Tenant $t2;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true]);
        $this->ctx = app(TenantContext::class);
        $this->t2 = Tenant::factory()->create(['slug' => 't2', 'primary_domain' => 't2.example']);
    }

    public function test_api_rooms_list_is_isolated_per_tenant(): void
    {
        $defaultId = Tenant::where('is_default', true)->value('id');

        $this->ctx->runFor($defaultId, fn () => Resource::factory()->create([
            'type' => 'room', 'name' => 'Ruang Default', 'status' => 'active',
        ]));
        $u2 = $this->ctx->runFor($this->t2->id, function () {
            Resource::factory()->create(['type' => 'room', 'name' => 'Ruang Tenant Dua', 'status' => 'active']);

            return User::factory()->create();
        });

        Sanctum::actingAs($u2, ['read']);

        $this->getJson('http://t2.example/api/v1/rooms')
            ->assertOk()
            ->assertSee('Ruang Tenant Dua')
            ->assertDontSee('Ruang Default');
    }

    public function test_idor_a_tenant_cannot_reach_another_tenants_resource(): void
    {
        $defaultId = Tenant::where('is_default', true)->value('id');

        $defaultRoom = $this->ctx->runFor($defaultId, fn () => Resource::factory()->create(['type' => 'room', 'status' => 'active']));
        $u2 = $this->ctx->runFor($this->t2->id, fn () => User::factory()->create());

        Sanctum::actingAs($u2, ['read']);

        // Tenant 2 asking for the default tenant's room → scoped out → 404.
        $this->getJson("http://t2.example/api/v1/rooms/{$defaultRoom->id}/availability?starts_at=2026-07-01T02:00:00Z&ends_at=2026-07-01T03:00:00Z")
            ->assertNotFound();
    }

    public function test_api_bookings_index_only_returns_callers_tenant(): void
    {
        $defaultId = Tenant::where('is_default', true)->value('id');

        // A booking in the default tenant (different requester).
        $this->ctx->runFor($defaultId, fn () => Booking::factory()->approved()->create());

        $u2 = $this->ctx->runFor($this->t2->id, function () {
            $user = User::factory()->create();
            Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

            return $user;
        });

        Sanctum::actingAs($u2, ['read']);

        $this->getJson('http://t2.example/api/v1/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data'); // only tenant 2's booking
    }
}
