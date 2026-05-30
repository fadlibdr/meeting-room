<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Http\Controllers\BookingController;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Role;
use App\Models\User;
use App\Policies\BookingPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Feature tests for GET /bookings/{booking} (bookings.show).
 *
 * Covers authorization (who may open a booking detail page) and the
 * approval-timeline rendering. Authorization is enforced by the
 * route-level can:view,booking middleware -> BookingPolicy::view.
 *
 * @see BookingController::show
 * @see BookingPolicy::view
 */
class ShowBookingTest extends TestCase
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

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    public function test_owner_can_view_their_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $booking = Booking::factory()->create([
            'requester_user_id' => $owner->id,
            'status' => BookingStatus::Approved,
        ]);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee($booking->booking_code);
    }

    public function test_assigned_approver_can_view_the_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $approver = $this->userWithRole('unit_approver');
        $booking = Booking::factory()->create([
            'requester_user_id' => $owner->id,
            'status' => BookingStatus::Submitted,
            'current_approver_user_id' => $approver->id,
            'current_approval_step' => 1,
        ]);

        $this->actingAs($approver)
            ->get(route('bookings.show', $booking))
            ->assertOk();
    }

    public function test_ga_admin_can_view_any_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $gaAdmin = $this->userWithRole('ga_admin');
        $booking = Booking::factory()->create([
            'requester_user_id' => $owner->id,
            'status' => BookingStatus::Approved,
        ]);

        $this->actingAs($gaAdmin)
            ->get(route('bookings.show', $booking))
            ->assertOk();
    }

    public function test_super_admin_can_view_any_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $superAdmin = $this->userWithRole('super_admin');
        $booking = Booking::factory()->create([
            'requester_user_id' => $owner->id,
            'status' => BookingStatus::Approved,
        ]);

        $this->actingAs($superAdmin)
            ->get(route('bookings.show', $booking))
            ->assertOk();
    }

    public function test_unrelated_requester_cannot_view_others_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $intruder = $this->userWithRole('requester');
        $booking = Booking::factory()->create([
            'requester_user_id' => $owner->id,
            'status' => BookingStatus::Approved,
        ]);

        $this->actingAs($intruder)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $owner = $this->userWithRole('requester');
        $booking = Booking::factory()->create([
            'requester_user_id' => $owner->id,
            'status' => BookingStatus::Approved,
        ]);

        $this->get(route('bookings.show', $booking))
            ->assertRedirect(route('login'));
    }

    public function test_timeline_renders_status_history(): void
    {
        $owner = $this->userWithRole('requester');
        $booking = Booking::factory()->create([
            'requester_user_id' => $owner->id,
            'status' => BookingStatus::Approved,
        ]);
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => 'submitted',
            'to_status' => 'approved',
            'changed_by_user_id' => $owner->id,
            'change_reason' => 'Disetujui sesuai jadwal yang diajukan',
            'changed_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Disetujui sesuai jadwal yang diajukan');
    }

    public function test_timeline_is_ordered_chronologically(): void
    {
        $owner = $this->userWithRole('requester');
        $booking = Booking::factory()->create([
            'requester_user_id' => $owner->id,
            'status' => BookingStatus::Approved,
        ]);
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => 'submitted',
            'changed_by_user_id' => $owner->id,
            'change_reason' => 'PERISTIWA-AWAL-pengajuan',
            'changed_at' => now()->subHours(3),
        ]);
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => 'submitted',
            'to_status' => 'approved',
            'changed_by_user_id' => $owner->id,
            'change_reason' => 'PERISTIWA-AKHIR-persetujuan',
            'changed_at' => now()->subHour(),
        ]);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSeeInOrder(['PERISTIWA-AWAL-pengajuan', 'PERISTIWA-AKHIR-persetujuan']);
    }
}
