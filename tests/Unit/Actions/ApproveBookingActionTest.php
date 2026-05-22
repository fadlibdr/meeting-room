<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\ApproveBookingAction;
use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
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
use Tests\TestCase;

/**
 * Tests for App\Actions\ApproveBookingAction.
 *
 * Covers Sprint 2D Phase A. Validates that the action:
 *  - Transitions a Submitted booking to Approved atomically
 *  - Updates the BookingApproval row (status, action_at, action_notes, acted_by_user_id)
 *  - Clears the hybrid pointer (current_approval_step + current_approver_user_id) — Dec-03
 *  - Writes BookingStatusHistory + ActivityLog inside the same transaction
 *  - Re-checks conflict and rolls back if a conflict appeared between submit and approve
 *  - Refuses to approve a booking that is no longer in Submitted status
 *
 * @see ApproveBookingAction
 */
class ApproveBookingActionTest extends TestCase
{
    use RefreshDatabase;

    private ApproveBookingAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->action = $this->app->make(ApproveBookingAction::class);
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
    private function createSubmittedBooking(?User $approver = null, ?Room $room = null): Booking
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $approver ??= User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $this->attachRole($approver, 'unit_approver');
        $room ??= Room::factory()->create([
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

    public function test_approves_a_submitted_booking(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $approved = $this->action->execute($booking, $approver, 'Disetujui untuk dipakai.');

        $this->assertSame(BookingStatus::Approved, $approved->status);
        $this->assertNotNull($approved->approved_at);
        $this->assertNull($approved->current_approval_step);
        $this->assertNull($approved->current_approver_user_id);
    }

    public function test_updates_booking_approval_row_with_action_metadata(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $this->action->execute($booking, $approver, 'Setuju, ruangan tersedia.');

        $approvalRow = BookingApproval::where('booking_id', $booking->id)
            ->where('sequence_no', 1)
            ->firstOrFail();

        $this->assertSame('approved', $approvalRow->status);
        $this->assertNotNull($approvalRow->action_at);
        $this->assertSame('Setuju, ruangan tersedia.', $approvalRow->action_notes);
        $this->assertSame($approver->id, $approvalRow->acted_by_user_id);
    }

    public function test_writes_status_history_for_submitted_to_approved_transition(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $this->action->execute($booking, $approver);

        $history = BookingStatusHistory::where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('submitted', $history->from_status);
        $this->assertSame('approved', $history->to_status);
        $this->assertSame($approver->id, $history->changed_by_user_id);
    }

    public function test_writes_activity_log_for_approve_event(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $this->action->execute($booking, $approver, 'OK.');

        $log = ActivityLog::where('subject_type', Booking::class)
            ->where('subject_id', $booking->id)
            ->where('event', 'approve')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($approver->id, $log->actor_user_id);
        $this->assertSame('bookings', $log->module);
    }

    public function test_approves_with_null_notes(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        $approved = $this->action->execute($booking, $approver, null);

        $this->assertSame(BookingStatus::Approved, $approved->status);

        $approvalRow = BookingApproval::where('booking_id', $booking->id)->first();
        $this->assertNull($approvalRow->action_notes);
    }

    // ─── RACE / CONFLICT PATHS ───────────────────────────────────────

    public function test_throws_conflict_when_slot_was_taken_between_submit_and_approve(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        // Simulate another booking grabbing an overlapping slot AFTER submit
        // but BEFORE approve. This is the race the re-check is meant to catch.
        $otherRequester = User::factory()->create();
        Booking::create([
            'booking_code' => 'BKG-20260505-RACE',
            'room_id' => $booking->room_id,
            'requester_user_id' => $otherRequester->id,
            'created_by_user_id' => $otherRequester->id,
            'subject' => 'Conflicting booking',
            'attendee_count' => 3,
            'starts_at' => '2026-05-05 10:30:00',  // overlaps 10:00-11:00
            'ends_at' => '2026-05-05 11:30:00',
            'status' => BookingStatus::Approved->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'none',
            'approved_at' => Carbon::now(),
        ]);

        $this->expectException(BookingConflictException::class);

        $this->action->execute($booking, $approver);
    }

    public function test_rolls_back_all_writes_on_conflict(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        // Plant the conflicting booking
        Booking::create([
            'booking_code' => 'BKG-20260505-RACE',
            'room_id' => $booking->room_id,
            'requester_user_id' => User::factory()->create()->id,
            'created_by_user_id' => User::factory()->create()->id,
            'subject' => 'Conflict',
            'attendee_count' => 1,
            'starts_at' => '2026-05-05 10:30:00',
            'ends_at' => '2026-05-05 11:30:00',
            'status' => BookingStatus::Approved->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'none',
            'approved_at' => Carbon::now(),
        ]);

        $logCountBefore = ActivityLog::count();
        $historyCountBefore = BookingStatusHistory::count();

        try {
            $this->action->execute($booking, $approver);
            $this->fail('Expected BookingConflictException to be thrown');
        } catch (BookingConflictException $e) {
            // Expected
        }

        // Verify no side effects from the failed approval
        $this->assertSame($logCountBefore, ActivityLog::count(), 'ActivityLog should not have grown');
        $this->assertSame($historyCountBefore, BookingStatusHistory::count(), 'BookingStatusHistory should not have grown');

        // Booking still in Submitted state, approval row still pending
        $booking = $booking->fresh();
        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertNotNull($booking->current_approver_user_id);

        $approvalRow = BookingApproval::where('booking_id', $booking->id)->first();
        $this->assertSame('pending', $approvalRow->status);
    }

    // ─── INVALID STATE PATHS ─────────────────────────────────────────

    public function test_refuses_to_approve_a_booking_that_is_not_submitted(): void
    {
        $booking = $this->createSubmittedBooking();
        $approver = $booking->currentApprover;

        // Manually transition booking to Approved (simulating another path
        // having changed it between rendering inbox and approver clicking)
        $booking->update([
            'status' => BookingStatus::Approved->value,
            'approved_at' => Carbon::now(),
            'current_approval_step' => null,
            'current_approver_user_id' => null,
        ]);

        $this->expectException(DomainException::class);

        $this->action->execute($booking->fresh(), $approver);
    }
}
