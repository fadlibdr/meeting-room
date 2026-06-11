<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Http\Controllers\BookingController;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Integration tests for POST /bookings/{booking}/cancel.
 *
 * Exercises the full HTTP pipeline:
 *   CancelBookingRequest (authorize via BookingPolicy::cancel + validate
 *     the conditionally-required reason)
 *     -> BookingController::cancel
 *     -> CancelBookingAction
 *     -> redirect to show with flash, OR 403, OR back with errors.
 *
 * Complements Tests\Unit\Actions\CancelBookingActionTest (the action in
 * isolation); these tests guarantee the request -> controller -> action
 * wiring is correct. Covers M3 Phase B.
 *
 * @see BookingController
 * @see CancelBookingRequest
 */
class CancelBookingTest extends TestCase
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

    private function attachRole(User $user, string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);

        return $user->fresh();
    }

    private function makeRequester(): User
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        return $this->attachRole($user, 'requester');
    }

    private function makeBooking(BookingStatus $status, User $owner): Booking
    {
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $attributes = [
            'booking_code' => 'BKG-20260506-'.strtoupper($status->value),
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Uji',
            'attendee_count' => 5,
            'starts_at' => '2026-05-06 10:00:00',
            'ends_at' => '2026-05-06 11:00:00',
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

        if ($status === BookingStatus::Approved) {
            $approver = User::factory()->create(['is_active' => true]);
            $attributes['submitted_at'] = Carbon::now()->subHour();
            $attributes['approved_at'] = Carbon::now();
            $booking = Booking::create($attributes);
            BookingApproval::create([
                'booking_id' => $booking->id,
                'sequence_no' => 1,
                'approver_user_id' => $approver->id,
                'status' => 'approved',
                'action_at' => Carbon::now(),
                'acted_by_user_id' => $approver->id,
            ]);

            return $booking;
        }

        return Booking::create($attributes);
    }

    // ─── HAPPY PATHS ─────────────────────────────────────────────────

    public function test_owner_can_cancel_their_draft_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);

        $response = $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.show', $booking));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Cancelled->value,
        ]);
    }

    public function test_owner_can_cancel_their_submitted_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Submitted, $owner);

        $response = $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertNull($booking->current_approval_step);
        $this->assertNull($booking->current_approver_user_id);

        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'status' => 'cancelled',
        ]);
    }

    public function test_owner_can_cancel_their_approved_booking_with_a_reason(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Approved, $owner);

        $response = $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking), [
                'cancellation_reason' => 'Rapat dibatalkan oleh penyelenggara.',
            ]);

        $response->assertRedirect(route('bookings.show', $booking));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Cancelled->value,
            'cancellation_reason' => 'Rapat dibatalkan oleh penyelenggara.',
        ]);
    }

    public function test_cancelling_a_draft_booking_without_a_reason_succeeds(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);

        $response = $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('bookings.show', $booking));
    }

    // ─── VALIDATION ──────────────────────────────────────────────────

    public function test_cancelling_an_approved_booking_without_a_reason_fails_validation(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Approved, $owner);

        $response = $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking));

        $response->assertSessionHasErrors(['cancellation_reason']);

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Approved->value,
        ]);
    }

    // ─── AUTHORIZATION ───────────────────────────────────────────────

    public function test_a_non_owner_cannot_cancel_the_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Submitted, $owner);

        $stranger = $this->makeRequester();

        $response = $this->actingAs($stranger)
            ->post(route('bookings.cancel', $booking), [
                'cancellation_reason' => 'Bukan booking saya.',
            ]);

        $response->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Submitted->value,
        ]);
    }

    public function test_cannot_cancel_a_rejected_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Rejected, $owner);

        $response = $this->actingAs($owner)
            ->post(route('bookings.cancel', $booking));

        // BookingPolicy::cancel gates terminal statuses — authorize() fails.
        $response->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Rejected->value,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);

        $response = $this->post(route('bookings.cancel', $booking));

        $response->assertRedirect(route('login'));

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Draft->value,
        ]);
    }
}
