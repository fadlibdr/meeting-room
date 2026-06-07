<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CancelBookingAction;
use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Tests for App\Actions\CancelBookingAction.
 *
 * Covers M3 Phase A. Validates that the action:
 *  - Cancels Draft / Submitted / Approved bookings atomically
 *  - Refuses Rejected / Cancelled / Completed bookings (DomainException)
 *  - Requires a reason only when the booking is Approved (Blueprint H.5)
 *  - Clears the Dec-03 hybrid pointer on a Submitted cancellation, and
 *    cancels that booking's pending booking_approvals row
 *  - Leaves an Approved booking's approval rows intact as history
 *  - Notifies the assigned approver — except for Draft cancellations and
 *    when notify: false is passed (M3-Dec-3 reschedule suppression)
 *  - Writes BookingStatusHistory + ActivityLog inside the same transaction
 *
 * @see CancelBookingAction
 */
class CancelBookingActionTest extends TestCase
{
    use RefreshDatabase;

    private CancelBookingAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->action = $this->app->make(CancelBookingAction::class);
        Carbon::setTestNow('2026-05-05 09:00:00');
        Notification::fake();
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
     * Build a booking in the given status, with the booking_approvals
     * row(s) that match the post-Submit / post-Approve state.
     *
     * @return array{booking: Booking, approver: User, requester: User}
     */
    private function makeBooking(BookingStatus $status): array
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $approver = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $this->attachRole($approver, 'unit_approver');
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $attributes = [
            'booking_code' => 'BKG-20260505-'.strtoupper($status->value),
            'resource_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $unit->id,
            'created_by_user_id' => $requester->id,
            'subject' => 'Test Meeting',
            'attendee_count' => 5,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
            'status' => $status->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
        ];

        if ($status === BookingStatus::Submitted) {
            $attributes['submitted_at'] = Carbon::now();
            $attributes['current_approval_step'] = 1;
            $attributes['current_approver_user_id'] = $approver->id;
        }

        if ($status === BookingStatus::Approved) {
            $attributes['submitted_at'] = Carbon::now()->subHour();
            $attributes['approved_at'] = Carbon::now();
        }

        $booking = Booking::create($attributes);

        if ($status === BookingStatus::Submitted) {
            BookingApproval::create([
                'booking_id' => $booking->id,
                'sequence_no' => 1,
                'approver_user_id' => $approver->id,
                'status' => 'pending',
            ]);
        }

        if ($status === BookingStatus::Approved) {
            BookingApproval::create([
                'booking_id' => $booking->id,
                'sequence_no' => 1,
                'approver_user_id' => $approver->id,
                'status' => 'approved',
                'action_at' => Carbon::now(),
                'acted_by_user_id' => $approver->id,
            ]);
        }

        return [
            'booking' => $booking->fresh(['approvals']),
            'approver' => $approver,
            'requester' => $requester,
        ];
    }

    // ─── DRAFT CANCELLATION ──────────────────────────────────────────

    public function test_cancels_a_draft_booking(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Draft);

        $cancelled = $this->action->execute($booking, $requester);

        $this->assertSame(BookingStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
        $this->assertNull($cancelled->current_approval_step);
        $this->assertNull($cancelled->current_approver_user_id);
    }

    public function test_draft_cancellation_succeeds_without_a_reason(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Draft);

        $cancelled = $this->action->execute($booking, $requester);

        $this->assertSame(BookingStatus::Cancelled, $cancelled->status);
        $this->assertNull($cancelled->cancellation_reason);
    }

    public function test_draft_cancellation_dispatches_no_notification(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Draft);

        $this->action->execute($booking, $requester);

        Notification::assertNothingSent();
    }

    // ─── SUBMITTED CANCELLATION ──────────────────────────────────────

    public function test_cancels_a_submitted_booking(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Submitted);

        $cancelled = $this->action->execute($booking, $requester);

        $this->assertSame(BookingStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_submitted_cancellation_clears_the_hybrid_pointer(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Submitted);

        $cancelled = $this->action->execute($booking, $requester);

        $this->assertNull($cancelled->current_approval_step);
        $this->assertNull($cancelled->current_approver_user_id);
    }

    public function test_submitted_cancellation_cancels_the_pending_approval_row(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Submitted);

        $this->action->execute($booking, $requester);

        $approvalRow = BookingApproval::where('booking_id', $booking->id)
            ->where('sequence_no', 1)
            ->firstOrFail();

        $this->assertSame('cancelled', $approvalRow->status);
        $this->assertNotNull($approvalRow->action_at);
        $this->assertSame($requester->id, $approvalRow->acted_by_user_id);
    }

    public function test_submitted_cancellation_notifies_the_assigned_approver(): void
    {
        ['booking' => $booking, 'requester' => $requester, 'approver' => $approver]
            = $this->makeBooking(BookingStatus::Submitted);

        $this->action->execute($booking, $requester);

        Notification::assertSentTo($approver, BookingCancelledNotification::class);
    }

    public function test_writes_status_history_for_submitted_to_cancelled_transition(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Submitted);

        $this->action->execute($booking, $requester);

        $history = BookingStatusHistory::where('booking_id', $booking->id)
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('submitted', $history->from_status);
        $this->assertSame('cancelled', $history->to_status);
        $this->assertSame($requester->id, $history->changed_by_user_id);
    }

    // ─── APPROVED CANCELLATION ───────────────────────────────────────

    public function test_cancels_an_approved_booking_with_a_reason(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Approved);

        $cancelled = $this->action->execute(
            $booking,
            $requester,
            'Rapat dibatalkan oleh penyelenggara.',
        );

        $this->assertSame(BookingStatus::Cancelled, $cancelled->status);
        $this->assertSame('Rapat dibatalkan oleh penyelenggara.', $cancelled->cancellation_reason);
    }

    public function test_approved_cancellation_leaves_the_approval_row_intact(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Approved);

        $this->action->execute($booking, $requester, 'Tidak jadi.');

        $approvalRow = BookingApproval::where('booking_id', $booking->id)
            ->where('sequence_no', 1)
            ->firstOrFail();

        // The approval genuinely happened — its row stays as history.
        $this->assertSame('approved', $approvalRow->status);
    }

    public function test_approved_cancellation_notifies_the_assigned_approver(): void
    {
        ['booking' => $booking, 'requester' => $requester, 'approver' => $approver]
            = $this->makeBooking(BookingStatus::Approved);

        $this->action->execute($booking, $requester, 'Tidak jadi.');

        Notification::assertSentTo($approver, BookingCancelledNotification::class);
    }

    // ─── REASON ENFORCEMENT (Blueprint H.5) ──────────────────────────

    public function test_throws_when_cancelling_an_approved_booking_without_a_reason(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Approved);

        $this->expectException(InvalidArgumentException::class);

        $this->action->execute($booking, $requester);
    }

    public function test_throws_when_cancelling_an_approved_booking_with_a_blank_reason(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Approved);

        $this->expectException(InvalidArgumentException::class);

        $this->action->execute($booking, $requester, '   ');
    }

    // ─── NOTIFICATION SUPPRESSION (M3-Dec-3) ─────────────────────────

    public function test_notify_false_suppresses_the_cancel_notification(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Approved);

        $this->action->execute($booking, $requester, 'Dijadwalkan ulang.', notify: false);

        Notification::assertNothingSent();
    }

    // ─── INVALID SOURCE STATUS ───────────────────────────────────────

    public function test_refuses_to_cancel_a_rejected_booking(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Rejected);

        $this->expectException(DomainException::class);

        $this->action->execute($booking, $requester);
    }

    public function test_refuses_to_cancel_an_already_cancelled_booking(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Cancelled);

        $this->expectException(DomainException::class);

        $this->action->execute($booking, $requester);
    }

    public function test_refuses_to_cancel_a_completed_booking(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Completed);

        $this->expectException(DomainException::class);

        $this->action->execute($booking, $requester);
    }

    // ─── AUDIT ───────────────────────────────────────────────────────

    public function test_writes_activity_log_for_cancel_event(): void
    {
        ['booking' => $booking, 'requester' => $requester] = $this->makeBooking(BookingStatus::Submitted);

        $this->action->execute($booking, $requester);

        $log = ActivityLog::where('subject_type', Booking::class)
            ->where('subject_id', $booking->id)
            ->where('event', 'cancel')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame($requester->id, $log->actor_user_id);
        $this->assertSame('bookings', $log->module);
    }
}
