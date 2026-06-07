<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\SubmitBookingAction;
use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Http\Controllers\BookingController;
use App\Http\Requests\Booking\StoreBookingRequest;
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
 * Integration tests for POST /bookings.
 *
 * Exercises the full HTTP pipeline:
 *   StoreBookingRequest (auth + validate)
 *     -> BookingPolicy::create (authorize)
 *     -> SubmitBookingAction (execute domain logic)
 *     -> redirect with flash OR back with field errors
 *
 * Complements Tests\Unit\Actions\SubmitBookingActionTest which
 * tests the action in isolation. These tests guarantee the wiring
 * between layers is correct.
 *
 * @see BookingController
 * @see StoreBookingRequest
 * @see SubmitBookingAction
 */
class SubmitBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Tuesday, 09:00 — within typical operating hours
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

    private function makeRequester(?User $approver = null, ?Unit $unit = null): User
    {
        $unit ??= Unit::factory()->create();

        $user = User::factory()->create([
            'unit_id' => $unit->id,
            'approver_user_id' => $approver?->id,
            'is_active' => true,
        ]);

        return $this->attachRole($user, 'requester');
    }

    /**
     * @return array<string, string|int>
     */
    private function validPayload(Room $room): array
    {
        return [
            'resource_id' => $room->id,
            'subject' => 'Rapat Mingguan Tim',
            'agenda' => 'Review sprint progress dan planning sprint berikutnya.',
            'attendee_count' => 5,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ];
    }

    // ─── HAPPY PATH ──────────────────────────────────────────────────

    public function test_authenticated_requester_can_submit_booking_to_auto_approve_room(): void
    {
        $requester = $this->makeRequester();
        $room = Room::factory()->create([
            'approval_mode' => RoomApprovalMode::None,
            'is_active' => true,
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $response = $this->actingAs($requester)
            ->post(route('bookings.store'), $this->validPayload($room));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'resource_id' => $room->id,
            'requester_user_id' => $requester->id,
            'status' => BookingStatus::Approved->value,
            'subject' => 'Rapat Mingguan Tim',
        ]);
    }

    public function test_authenticated_requester_can_submit_booking_to_unit_approver_room(): void
    {
        $unit = Unit::factory()->create();
        $approver = User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $this->attachRole($approver, 'unit_approver');

        $requester = $this->makeRequester($approver, $unit);
        $room = Room::factory()->create([
            'approval_mode' => RoomApprovalMode::UnitApprover,
            'is_active' => true,
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $response = $this->actingAs($requester)
            ->post(route('bookings.store'), $this->validPayload($room));

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('success');

        $booking = Booking::where('requester_user_id', $requester->id)->firstOrFail();

        $this->assertSame(BookingStatus::Submitted->value, $booking->status->value);
        $this->assertSame($approver->id, $booking->current_approver_user_id);
        $this->assertSame(1, $booking->current_approval_step);

        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
        ]);
    }

    // ─── DOMAIN ERROR MAPPING ────────────────────────────────────────

    public function test_conflict_with_existing_booking_returns_field_error_and_no_booking_created(): void
    {
        $requester = $this->makeRequester();
        $room = Room::factory()->create([
            'approval_mode' => RoomApprovalMode::None,
            'is_active' => true,
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        // Pre-existing approved booking 10:00–11:00
        Booking::factory()->create([
            'resource_id' => $room->id,
            'status' => BookingStatus::Approved->value,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ]);

        $bookingsBefore = Booking::count();

        $response = $this->actingAs($requester)
            ->post(route('bookings.store'), $this->validPayload($room));

        $response->assertRedirect();
        $response->assertSessionHasErrors(['starts_at']);
        $response->assertSessionDoesntHaveErrors(['resource_id']);

        // No new booking written
        $this->assertSame($bookingsBefore, Booking::count());
    }

    public function test_unit_approver_required_but_requester_has_none_returns_room_id_error(): void
    {
        // Requester has approver_user_id = null
        $requester = $this->makeRequester(approver: null);
        $room = Room::factory()->create([
            'approval_mode' => RoomApprovalMode::UnitApprover,
            'is_active' => true,
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $response = $this->actingAs($requester)
            ->post(route('bookings.store'), $this->validPayload($room));

        $response->assertRedirect();
        $response->assertSessionHasErrors(['resource_id']);

        $errors = session('errors')->get('resource_id');
        $this->assertStringContainsString('approver', strtolower($errors[0]));

        $this->assertDatabaseCount('bookings', 0);
    }

    // ─── VALIDATION (handled by StoreBookingRequest, but verify wiring) ───

    public function test_invalid_payload_ends_at_before_starts_at_returns_validation_error(): void
    {
        $requester = $this->makeRequester();
        $room = Room::factory()->create([
            'approval_mode' => RoomApprovalMode::None,
            'is_active' => true,
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $payload = $this->validPayload($room);
        $payload['ends_at'] = '2026-05-05 09:30:00'; // before starts_at

        $response = $this->actingAs($requester)
            ->post(route('bookings.store'), $payload);

        $response->assertSessionHasErrors(['ends_at']);
        $this->assertDatabaseCount('bookings', 0);
    }

    // ─── AUTHENTICATION ──────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $room = Room::factory()->create([
            'approval_mode' => RoomApprovalMode::None,
            'is_active' => true,
            'capacity' => 10,
        ]);

        $response = $this->post(route('bookings.store'), $this->validPayload($room));

        $response->assertRedirect(route('login'));
        $this->assertDatabaseCount('bookings', 0);
    }

    // ─── INACTIVE USER (per user.active middleware) ──────────────────

    public function test_inactive_user_cannot_submit(): void
    {
        $requester = $this->makeRequester();
        $requester->update(['is_active' => false]);

        $room = Room::factory()->create([
            'approval_mode' => RoomApprovalMode::None,
            'is_active' => true,
            'capacity' => 10,
        ]);

        $response = $this->actingAs($requester)
            ->post(route('bookings.store'), $this->validPayload($room));

        // user.active middleware blocks deactivated users — exact behavior
        // (redirect to login vs logout + flash) is implementation-detail.
        // What matters for THIS test: the booking was not created.
        $this->assertDatabaseCount('bookings', 0);

        // Sanity: response is not a successful redirect to dashboard
        // (which would mean the middleware let the request through).
        $this->assertFalse(
            $response->isRedirect(route('dashboard')),
            'Inactive user should not have been allowed to submit a booking.'
        );
    }
}
