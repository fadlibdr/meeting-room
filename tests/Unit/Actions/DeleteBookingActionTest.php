<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\DeleteBookingAction;
use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tests for App\Actions\DeleteBookingAction.
 *
 * Covers M3 Phase F. Validates that the action:
 *  - Hard-deletes a Draft booking (the row is gone)
 *  - Refuses every non-Draft status (DomainException)
 *  - Leaves the booking intact when the delete is refused
 *  - Lets the DB cascade booking_status_histories
 *  - Writes an activity_logs row that SURVIVES the hard delete
 *    (M3-Dec-4: hard, but auditable)
 *
 * The action is actor-agnostic — no roles are seeded; authorization is
 * BookingPolicy::delete's job and is covered by BookingPolicyTest.
 *
 * @see DeleteBookingAction
 */
class DeleteBookingActionTest extends TestCase
{
    use RefreshDatabase;

    private DeleteBookingAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = $this->app->make(DeleteBookingAction::class);
        Carbon::setTestNow('2026-05-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeActor(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function makeBooking(BookingStatus $status): Booking
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        return Booking::create([
            'booking_code' => 'BKG-20260512-'.strtoupper($status->value),
            'resource_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $unit->id,
            'created_by_user_id' => $requester->id,
            'subject' => 'Test Meeting',
            'attendee_count' => 5,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 11:00:00',
            'status' => $status->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
        ]);
    }

    // ─── HAPPY PATH ──────────────────────────────────────────────────

    public function test_deletes_a_draft_booking(): void
    {
        $booking = $this->makeBooking(BookingStatus::Draft);

        $this->action->execute($booking, $this->makeActor());

        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }

    // ─── INVALID SOURCE STATUS ───────────────────────────────────────

    public function test_refuses_to_delete_a_submitted_booking(): void
    {
        $booking = $this->makeBooking(BookingStatus::Submitted);

        $this->expectException(DomainException::class);

        $this->action->execute($booking, $this->makeActor());
    }

    public function test_refuses_to_delete_an_approved_booking(): void
    {
        $booking = $this->makeBooking(BookingStatus::Approved);

        $this->expectException(DomainException::class);

        $this->action->execute($booking, $this->makeActor());
    }

    public function test_refuses_to_delete_a_rejected_booking(): void
    {
        $booking = $this->makeBooking(BookingStatus::Rejected);

        $this->expectException(DomainException::class);

        $this->action->execute($booking, $this->makeActor());
    }

    public function test_refuses_to_delete_a_cancelled_booking(): void
    {
        $booking = $this->makeBooking(BookingStatus::Cancelled);

        $this->expectException(DomainException::class);

        $this->action->execute($booking, $this->makeActor());
    }

    public function test_refuses_to_delete_a_completed_booking(): void
    {
        $booking = $this->makeBooking(BookingStatus::Completed);

        $this->expectException(DomainException::class);

        $this->action->execute($booking, $this->makeActor());
    }

    public function test_a_refused_delete_leaves_the_booking_intact(): void
    {
        $booking = $this->makeBooking(BookingStatus::Submitted);

        try {
            $this->action->execute($booking, $this->makeActor());
        } catch (DomainException) {
            // expected — see test_refuses_to_delete_a_submitted_booking
        }

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    // ─── CASCADE ─────────────────────────────────────────────────────

    public function test_cascades_status_histories(): void
    {
        $booking = $this->makeBooking(BookingStatus::Draft);
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => BookingStatus::Draft->value,
            'changed_at' => Carbon::now(),
        ]);

        $this->action->execute($booking, $this->makeActor());

        $this->assertDatabaseMissing('booking_status_histories', [
            'booking_id' => $booking->id,
        ]);
    }

    // ─── AUDIT ───────────────────────────────────────────────────────

    public function test_writes_an_activity_log_that_survives_the_delete(): void
    {
        $booking = $this->makeBooking(BookingStatus::Draft);
        $actor = $this->makeActor();

        $this->action->execute($booking, $actor);

        $log = ActivityLog::where('subject_type', Booking::class)
            ->where('subject_id', $booking->id)
            ->where('event', 'delete')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($actor->id, $log->actor_user_id);
        $this->assertSame('bookings', $log->module);
        // The booking row is gone, but the audit row remains (M3-Dec-4).
        $this->assertDatabaseMissing('bookings', ['id' => $booking->id]);
    }
}
