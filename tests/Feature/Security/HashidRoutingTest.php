<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * End-to-end coverage that public resource URLs are masked with hashids so
 * sequential primary keys can't be enumerated or guessed (the id-guessing /
 * SQLi-by-binary-id surface).
 */
class HashidRoutingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-05-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeOwnerWithBooking(): array
    {
        $unit = Unit::factory()->create();
        $owner = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $role = Role::where('code', 'requester')->firstOrFail();
        $owner->roles()->attach($role->id, ['is_primary' => true, 'assigned_at' => now()]);

        $room = Room::factory()->create(['is_active' => true, 'status' => 'active', 'capacity' => 10]);
        $booking = Booking::create([
            'booking_code' => 'BKG-20260506-DRAFT',
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Uji',
            'attendee_count' => 5,
            'starts_at' => '2026-05-06 10:00:00',
            'ends_at' => '2026-05-06 11:00:00',
            'status' => BookingStatus::Draft->value,
            'source' => 'user',
        ]);

        return [$owner->fresh(), $booking];
    }

    public function test_booking_url_is_masked_and_contains_no_raw_id(): void
    {
        [, $booking] = $this->makeOwnerWithBooking();

        $url = route('bookings.show', $booking);

        $this->assertStringContainsString($booking->hashid, $url);
        $this->assertStringNotContainsString('/bookings/'.$booking->id, $url);
        $this->assertSame($booking->hashid, $booking->getRouteKey());
    }

    public function test_booking_resolves_through_its_hashid(): void
    {
        [$owner, $booking] = $this->makeOwnerWithBooking();

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertOk();
    }

    public function test_guessing_the_raw_integer_id_is_rejected(): void
    {
        [$owner, $booking] = $this->makeOwnerWithBooking();

        // The enumeration attempt: hit /bookings/{rawId} directly.
        $this->actingAs($owner)
            ->get('/bookings/'.$booking->id)
            ->assertNotFound();
    }

    public function test_tampered_hashid_is_rejected(): void
    {
        [$owner, $booking] = $this->makeOwnerWithBooking();

        $this->actingAs($owner)
            ->get('/bookings/'.$booking->hashid.'tampered')
            ->assertNotFound();
    }

    public function test_admin_user_edit_route_is_masked_and_enumeration_blocked(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $role = Role::where('code', 'super_admin')->firstOrFail();
        $admin->roles()->attach($role->id, ['is_primary' => true, 'assigned_at' => now()]);
        $admin = $admin->fresh();

        $target = User::factory()->create(['is_active' => true]);

        $masked = route('admin.users.edit', $target);
        $this->assertStringContainsString($target->hashid, $masked);

        $this->actingAs($admin)->get($masked)->assertOk();
        // Raw integer id no longer resolves.
        $this->actingAs($admin)->get('/admin/users/'.$target->id.'/edit')->assertNotFound();
    }
}
