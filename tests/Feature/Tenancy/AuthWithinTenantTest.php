<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Booking;
use App\Models\Resource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Stage 4a P2d — authentication is tenant-aware: login scopes by tenant (email
 * unique per-tenant), API tokens carry their own tenant regardless of host, and
 * the global-token public surfaces (calendar feed, signed check-in) resolve and
 * pin the right tenant.
 */
class AuthWithinTenantTest extends TestCase
{
    use RefreshDatabase;

    private TenantContext $ctx;

    private Tenant $t2;

    private int $defaultId;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.enabled' => true]);
        $this->ctx = app(TenantContext::class);
        $this->t2 = Tenant::factory()->create(['slug' => 't2', 'primary_domain' => 't2.example']);
        $this->defaultId = (int) Tenant::where('is_default', true)->value('id');
    }

    public function test_login_authenticates_within_the_resolved_tenant(): void
    {
        // Same email in two tenants (allowed by the per-tenant unique), different passwords.
        $this->ctx->runFor($this->defaultId, fn () => User::factory()->create(['email' => 'dup@x.test', 'password' => 'default-pw']));
        $u2 = $this->ctx->runFor($this->t2->id, fn () => User::factory()->create(['email' => 'dup@x.test', 'password' => 'tenant2-pw']));

        // Within tenant 2, the tenant-2 password authenticates the tenant-2 user.
        $this->ctx->set($this->t2->id);
        $this->assertTrue(Auth::attempt(['email' => 'dup@x.test', 'password' => 'tenant2-pw']));
        $this->assertSame($u2->id, Auth::id());

        // The OTHER tenant's password must NOT authenticate here.
        Auth::logout();
        $this->assertFalse(Auth::attempt(['email' => 'dup@x.test', 'password' => 'default-pw']));
    }

    public function test_api_token_carries_its_tenant_regardless_of_host(): void
    {
        $this->ctx->runFor($this->defaultId, fn () => Resource::factory()->create(['type' => 'room', 'name' => 'Ruang Default', 'status' => 'active']));
        $u2 = $this->ctx->runFor($this->t2->id, function () {
            Resource::factory()->create(['type' => 'room', 'name' => 'Ruang T2', 'status' => 'active']);

            return User::factory()->create();
        });

        Sanctum::actingAs($u2, ['read']);

        // Neutral host (resolves to default) — the token's own tenant must win.
        $this->getJson('http://localhost/api/v1/rooms')
            ->assertOk()
            ->assertSee('Ruang T2')
            ->assertDontSee('Ruang Default');
    }

    public function test_calendar_feed_resolves_a_non_default_tenant_by_token(): void
    {
        $u2 = $this->ctx->runFor($this->t2->id, function () {
            $user = User::factory()->create();
            Booking::factory()->approved()->create([
                'requester_user_id' => $user->id,
                'subject' => 'Rapat Tenant Dua',
                'starts_at' => now()->addDay(),
                'ends_at' => now()->addDay()->addHour(),
            ]);

            return $user;
        });
        $token = $u2->ensureCalendarFeedToken();

        // Feed hit on the app host (default context) — token must resolve t2.
        $this->get("http://localhost/calendar/feed/{$token}.ics")
            ->assertOk()
            ->assertSee('Rapat Tenant Dua');
    }

    public function test_signed_checkin_works_for_a_non_default_tenant_booking(): void
    {
        $booking = $this->ctx->runFor($this->t2->id, fn () => Booking::factory()->approved()->create([
            'starts_at' => now()->addMinutes(10),
            'ends_at' => now()->addHour(),
            'checked_in_at' => null,
        ]));

        $url = URL::signedRoute('bookings.checkin', ['booking' => $booking->id]);

        $this->get($url)->assertOk();
        $this->assertNotNull($booking->fresh()->checked_in_at);
    }
}
