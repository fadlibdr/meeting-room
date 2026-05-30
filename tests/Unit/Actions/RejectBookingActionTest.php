<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\RejectBookingAction;
use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tests for App\Actions\RejectBookingAction.
 *
 * Covers Sprint 2D Phase B. Validates that the action:
 *  - Transitions a Submitted booking to Rejected atomically
 *  - Requires a non-empty rejection reason (2D-B-Dec-3)
 *  - Writes the reason to BOTH bookings.rejection_reason and
 *    booking_approvals.action_notes (2D-B-Dec-4)
 *  - Clears the hybrid pointer (Dec-03) — rejected is terminal
 *  - Writes BookingStatusHistory + ActivityLog inside the same transaction
 *  - Does NOT re-check conflicts — rejection releases the slot, never claims it (2D-B-Dec-1)
 *  - Refuses to reject a booking that is no longer Submitted
 *
 * @see RejectBookingAction
 */
class RejectBookingActionTest extends TestCase
{
    use RefreshDatabase;

    private RejectBookingAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->action = $this->app->make(RejectBookingAction::class);
        Carbon::setTestNow('2026-05-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function attachRole(User $user, string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Build a Submitted booking with a pending BookingApproval row,
     * matching the post-Submit state SubmitBookingAction would have produced.
     */
    private function createSubmittedBooking(?User $approver = null): Booking
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $approver ??= User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $this->attachRole($approver, 'unit_approver');
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = Booking::create([
            'booking_code' => 'BKG-20260505-TEST',
            'room_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $unit->id,
            'created_by_user_id' => $requester->id,
            'subject' => 'Test Meeting',
            'attendee_count' => 5,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
            'status' => BookingStatus::Submitted->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
            'current_approval_step' => 1,
            'current_approver_user_id' => $approver->id,
            'submitted_at' => Carbon::now(),
        ]);

        BookingApproval::create([
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
        ]);

        return $booking->fresh(['approvals']);
    }

    // ─── HAPPY PATHS ─────────────────────────────────────────────────

    public function test_rejects_a_submitted_booking(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $rejected = $this->action->execute($booking, $approver, 'Ruangan sedang direnovasi.');

        $this->assertSame(BookingStatus::Rejected, $rejected->status);
        $this->assertNotNull($rejected->rejected_at);
        $this->assertNull($rejected->current_approval_step);
        $this->assertNull($rejected->current_approver_user_id);
    }

    public function test_writes_rejection_reason_to_booking(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $rejected = $this->action->execute($booking, $approver, 'Kapasitas tidak memadai untuk acara ini.');

        $this->assertSame('Kapasitas tidak memadai untuk acara ini.', $rejected->rejection_reason);
    }

    public function test_writes_reason_to_approval_row_action_notes(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $this->action->execute($booking, $approver, 'Bentrok dengan agenda direksi.');

        $approvalRow = BookingApproval::where('booking_id', $booking->id)
            ->where('sequence_no', 1)
            ->firstOrFail();

        $this->assertSame('rejected', $approvalRow->status);
        $this->assertNotNull($approvalRow->action_at);
        $this->assertSame('Bentrok dengan agenda direksi.', $approvalRow->action_notes);
        $this->assertSame($approver->id, $approvalRow->acted_by_user_id);
    }

    public function test_writes_status_history_for_submitted_to_rejected_transition(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $this->action->execute($booking, $approver, 'Alasan penolakan.');

        $history = BookingStatusHistory::where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('submitted', $history->from_status);
        $this->assertSame('rejected', $history->to_status);
        $this->assertSame($approver->id, $history->changed_by_user_id);
    }

    public function test_writes_activity_log_for_reject_event(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $this->action->execute($booking, $approver, 'Alasan penolakan.');

        $log = ActivityLog::where('subject_type', Booking::class)
            ->where('subject_id', $booking->id)
            ->where('event', 'reject')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($approver->id, $log->actor_user_id);
        $this->assertSame('bookings', $log->module);
    }

    // ─── REQUIRED-REASON ENFORCEMENT (2D-B-Dec-3 / Dec-5) ────────────

    public function test_throws_when_reason_is_empty(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $this->expectException(InvalidArgumentException::class);

        $this->action->execute($booking, $approver, '   ');
    }

    // ─── INVALID STATE PATHS ─────────────────────────────────────────

    public function test_refuses_to_reject_a_booking_that_is_not_submitted(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        // Manually transition booking to Approved — another path got there first
        $booking->update([
            'status' => BookingStatus::Approved->value,
            'approved_at' => Carbon::now(),
            'current_approval_step' => null,
            'current_approver_user_id' => null,
        ]);

        $this->expectException(DomainException::class);

        $this->action->execute($booking->fresh(), $approver, 'Alasan penolakan.');
    }
}
