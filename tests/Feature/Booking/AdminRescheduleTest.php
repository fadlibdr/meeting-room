<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRescheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', $roleCode)->firstOrFail()->id]);

        return $user;
    }

    private function approvedBookingFor(User $owner): Booking
    {
        $room = Room::factory()->create(['is_active' => true, 'status' => 'active']);

        return Booking::factory()->create([
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'status' => 'approved',
            'starts_at' => '2026-06-08 03:00:00',
            'ends_at' => '2026-06-08 04:00:00',
        ]);
    }

    public function test_ga_admin_can_open_reschedule_for_another_users_booking(): void
    {
        $admin = $this->userWithRole('ga_admin'); // has view-all, NOT cancel
        $owner = $this->userWithRole('requester');
        $booking = $this->approvedBookingFor($owner);

        // The new admin path matters precisely because GA Admin lacks bookings.cancel.
        $this->assertFalse($admin->hasPermission('bookings.cancel'));

        $this->actingAs($admin)
            ->get(route('bookings.reschedule', $booking->id))
            ->assertOk();
    }

    public function test_a_stranger_cannot_reschedule_someone_elses_booking(): void
    {
        $stranger = $this->userWithRole('requester');
        $owner = $this->userWithRole('requester');
        $booking = $this->approvedBookingFor($owner);

        $this->actingAs($stranger)
            ->get(route('bookings.reschedule', $booking->id))
            ->assertForbidden();
    }

    public function test_owner_can_reschedule_their_own_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $booking = $this->approvedBookingFor($owner);

        $this->actingAs($owner)
            ->get(route('bookings.reschedule', $booking->id))
            ->assertOk();
    }

    public function test_non_approved_booking_cannot_be_rescheduled(): void
    {
        $admin = $this->userWithRole('ga_admin');
        $owner = $this->userWithRole('requester');
        $booking = $this->approvedBookingFor($owner);
        $booking->update(['status' => 'submitted']);

        $this->actingAs($admin)
            ->get(route('bookings.reschedule', $booking->id))
            ->assertForbidden();
    }
}
