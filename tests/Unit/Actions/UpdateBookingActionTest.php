<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\UpdateBookingAction;
use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Unit tests for UpdateBookingAction (M3-C-i).
 *
 * Covers both paths of M3-Dec-1: a plain in-place Draft edit, and the
 * Submitted -> Draft revert (pointer cleared, pending approval cancelled,
 * status history written). Plus the conflict re-check (with self-exclusion),
 * the editable-status guard, and transaction atomicity.
 *
 * @see UpdateBookingAction
 */
class UpdateBookingActionTest extends TestCase
{
    use RefreshDatabase;

    private int $bookingSeq = 0;

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

    private function action(): UpdateBookingAction
    {
        return app(UpdateBookingAction::class);
    }

    private function makeRoom(): Room
    {
        return Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 20,
            'booking_buffer_minutes' => 0,
        ]);
    }

    private function makeBooking(
        BookingStatus $status,
        ?Room $room = null,
        string $startsAt = '2026-05-06 10:00:00',
        string $endsAt = '2026-05-06 11:00:00',
    ): Booking {
        $room ??= $this->makeRoom();
        $unit = Unit::factory()->create();
        $requester = User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        $attributes = [
            'booking_code' => sprintf('BKG-UPD-%06d', ++$this->bookingSeq),
            'resource_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $unit->id,
            'created_by_user_id' => $requester->id,
            'subject' => 'Rapat Awal',
            'agenda' => 'Agenda awal.',
            'attendee_count' => 4,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
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

    /**
     * @return array{resource_id: int, subject: string, agenda: string, attendee_count: int, starts_at: string, ends_at: string}
     */
    private function payload(
        Room $room,
        string $startsAt = '2026-05-06 14:00:00',
        string $endsAt = '2026-05-06 15:00:00',
    ): array {
        return [
            'resource_id' => $room->id,
            'subject' => 'Rapat Diperbarui',
            'agenda' => 'Agenda diperbarui.',
            'attendee_count' => 6,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];
    }

    // ─── DRAFT PATH (plain in-place save) ────────────────────────────

    public function test_updates_a_draft_booking_fields_in_place(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Draft, $room);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($room));

        $booking->refresh();
        $this->assertSame('Rapat Diperbarui', $booking->subject);
        $this->assertSame('Agenda diperbarui.', $booking->agenda);
        $this->assertSame(6, $booking->attendee_count);
        $this->assertSame('2026-05-06 14:00:00', $booking->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_draft_edit_keeps_status_draft_and_writes_no_status_history(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Draft, $room);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($room));

        $booking->refresh();
        $this->assertSame(BookingStatus::Draft, $booking->status);
        $this->assertDatabaseCount('booking_status_histories', 0);
    }

    public function test_records_the_editing_actor_as_updated_by(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Draft, $room);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($room));

        $this->assertSame($actor->id, $booking->fresh()->updated_by_user_id);
    }

    // ─── SUBMITTED PATH (M3-Dec-1 revert) ────────────────────────────

    public function test_editing_a_submitted_booking_reverts_it_to_draft(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Submitted, $room);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($room));

        $this->assertSame(BookingStatus::Draft, $booking->fresh()->status);
    }

    public function test_submitted_revert_clears_the_hybrid_pointer(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Submitted, $room);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($room));

        $booking->refresh();
        $this->assertNull($booking->current_approval_step);
        $this->assertNull($booking->current_approver_user_id);
        $this->assertNull($booking->submitted_at);
    }

    public function test_submitted_revert_cancels_the_pending_approval_row(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Submitted, $room);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($room));

        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'status' => 'cancelled',
        ]);
    }

    public function test_submitted_revert_writes_a_submitted_to_draft_status_history(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Submitted, $room);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($room));

        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => BookingStatus::Submitted->value,
            'to_status' => BookingStatus::Draft->value,
            'changed_by_user_id' => $actor->id,
        ]);
    }

    public function test_editing_keeping_the_same_slot_does_not_self_conflict(): void
    {
        $room = $this->makeRoom();
        // Submitted = locking status; without self-exclusion the conflict
        // re-check would find the booking conflicting with itself.
        $booking = $this->makeBooking(BookingStatus::Submitted, $room);
        $actor = User::factory()->create();

        $this->action()->execute(
            $booking,
            $actor,
            $this->payload($room, '2026-05-06 10:00:00', '2026-05-06 11:00:00'),
        );

        $booking->refresh();
        $this->assertSame(BookingStatus::Draft, $booking->status);
        $this->assertSame('Rapat Diperbarui', $booking->subject);
    }

    public function test_can_move_the_booking_to_a_different_room(): void
    {
        $originalRoom = $this->makeRoom();
        $newRoom = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Draft, $originalRoom);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($newRoom));

        $this->assertSame($newRoom->id, $booking->fresh()->resource_id);
    }

    // ─── AUDIT ───────────────────────────────────────────────────────

    public function test_writes_an_activity_log_for_the_update_event(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Draft, $room);
        $actor = User::factory()->create();

        $this->action()->execute($booking, $actor, $this->payload($room));

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'bookings',
            'event' => 'update',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'actor_user_id' => $actor->id,
        ]);
    }

    // ─── CONFLICT ────────────────────────────────────────────────────

    public function test_throws_a_conflict_when_the_new_slot_overlaps_another_booking(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Draft, $room);
        // An approved (locking) booking already occupies 14:00-15:00.
        $this->makeBooking(BookingStatus::Approved, $room, '2026-05-06 14:00:00', '2026-05-06 15:00:00');
        $actor = User::factory()->create();

        $this->expectException(BookingConflictException::class);

        $this->action()->execute(
            $booking,
            $actor,
            $this->payload($room, '2026-05-06 14:00:00', '2026-05-06 15:00:00'),
        );
    }

    public function test_rolls_back_every_write_when_a_conflict_is_detected(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Submitted, $room);
        $this->makeBooking(BookingStatus::Approved, $room, '2026-05-06 14:00:00', '2026-05-06 15:00:00');
        $actor = User::factory()->create();

        try {
            $this->action()->execute(
                $booking,
                $actor,
                $this->payload($room, '2026-05-06 14:00:00', '2026-05-06 15:00:00'),
            );
            $this->fail('Expected BookingConflictException was not thrown.');
        } catch (BookingConflictException) {
            // expected
        }

        $booking->refresh();
        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame('Rapat Awal', $booking->subject);
        $this->assertSame(1, $booking->current_approval_step);
        $this->assertNotNull($booking->current_approver_user_id);
        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('booking_status_histories', 0);
        $this->assertDatabaseMissing('activity_logs', [
            'subject_id' => $booking->id,
            'event' => 'update',
        ]);
    }

    // ─── STATUS GUARD ────────────────────────────────────────────────

    public function test_refuses_to_update_an_approved_booking(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Approved, $room);
        $actor = User::factory()->create();

        $this->expectException(DomainException::class);

        $this->action()->execute($booking, $actor, $this->payload($room));
    }

    public function test_refuses_to_update_a_rejected_booking(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Rejected, $room);
        $actor = User::factory()->create();

        $this->expectException(DomainException::class);

        $this->action()->execute($booking, $actor, $this->payload($room));
    }

    public function test_refuses_to_update_a_cancelled_booking(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Cancelled, $room);
        $actor = User::factory()->create();

        $this->expectException(DomainException::class);

        $this->action()->execute($booking, $actor, $this->payload($room));
    }

    public function test_refuses_to_update_a_completed_booking(): void
    {
        $room = $this->makeRoom();
        $booking = $this->makeBooking(BookingStatus::Completed, $room);
        $actor = User::factory()->create();

        $this->expectException(DomainException::class);

        $this->action()->execute($booking, $actor, $this->payload($room));
    }
}
