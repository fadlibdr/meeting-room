<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Http\Controllers\BookingController;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for the booking hard-delete route (M3-F-ii).
 *
 * Covers the wiring: the DELETE bookings/{booking} route, the
 * route-level can:delete gate, BookingController::destroy, and the
 * show-page "Hapus Permanen" button. The DeleteBookingAction mechanics
 * (cascade, audit-survives) are owned by DeleteBookingActionTest; the
 * Draft-only + permission policy matrix by BookingPolicyTest.
 *
 * @see BookingController::destroy
 */
class DeleteBookingTest extends TestCase
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
        $unit = Unit::factory()->create();
        $user = User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);

        return $user->fresh();
    }

    private function makeBooking(BookingStatus $status, User $owner): Booking
    {
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 20,
            'booking_buffer_minutes' => 0,
        ]);

        $attributes = [
            'booking_code' => 'BKG-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Uji',
            'agenda' => 'Agenda uji.',
            'attendee_count' => 4,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 11:00:00',
            'status' => $status->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
        ];

        if ($status === BookingStatus::Submitted) {
            $approver = User::factory()->create(['is_active' => true]);
            $attributes['submitted_at'] = Carbon::now();
            $attributes['current_approval_step'] = 1;
            $attributes['current_approver_user_id'] = $approver->id;
            $booking = Booking::create($attributes);
            BookingApproval::create([
                'booking_id' => $booking->id,
                'sequence_no' => 1,
                'approver_user_id' => $approver->id,
                'status' => 'pending',
            ]);

            return $booking;
        }

        return Booking::create($attributes);
    }

    // ─── DELETE ROUTE ────────────────────────────────────────────────

    public function test_super_admin_can_delete_a_draft_booking(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking(BookingStatus::Draft, $this->userWithRole('requester'));

        $this->actingAs($superAdmin)
            ->delete(route('bookings.destroy', $booking))
            ->assertRedirect(route('calendar.index'));

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    public function test_delete_is_forbidden_for_a_non_draft_booking(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking(BookingStatus::Submitted, $this->userWithRole('requester'));

        $this->actingAs($superAdmin)
            ->delete(route('bookings.destroy', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_a_requester_cannot_delete_their_own_draft(): void
    {
        $requester = $this->userWithRole('requester');
        $booking = $this->makeBooking(BookingStatus::Draft, $requester);

        $this->actingAs($requester)
            ->delete(route('bookings.destroy', $booking))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_a_ga_admin_cannot_delete_a_draft(): void
    {
        $gaAdmin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking(BookingStatus::Draft, $this->userWithRole('requester'));

        $this->actingAs($gaAdmin)
            ->delete(route('bookings.destroy', $booking))
            ->assertForbidden();
    }

    public function test_a_guest_is_redirected_from_the_delete_route(): void
    {
        $booking = $this->makeBooking(BookingStatus::Draft, $this->userWithRole('requester'));

        $this->delete(route('bookings.destroy', $booking))
            ->assertRedirect(route('login'));
    }

    // ─── SHOW-PAGE BUTTON ────────────────────────────────────────────

    public function test_show_page_offers_a_delete_button_for_a_super_admin_on_a_draft(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking(BookingStatus::Draft, $this->userWithRole('requester'));

        $this->actingAs($superAdmin)
            ->get(route('bookings.show', $booking))
            ->assertSee('Hapus Permanen');
    }

    public function test_show_page_hides_the_delete_button_from_a_requester(): void
    {
        $requester = $this->userWithRole('requester');
        $booking = $this->makeBooking(BookingStatus::Draft, $requester);

        $this->actingAs($requester)
            ->get(route('bookings.show', $booking))
            ->assertDontSee('Hapus Permanen');
    }

    public function test_show_page_hides_the_delete_button_for_a_non_draft_booking(): void
    {
        $superAdmin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking(BookingStatus::Submitted, $this->userWithRole('requester'));

        $this->actingAs($superAdmin)
            ->get(route('bookings.show', $booking))
            ->assertDontSee('Hapus Permanen');
    }
}
