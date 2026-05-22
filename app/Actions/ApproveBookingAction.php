<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Policies\BookingPolicy;
use App\Services\BookingConflictService;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Approves a Submitted booking through the full approval pipeline:
 *
 *  1. Lock target room row (lockForUpdate) for race safety
 *  2. Reload booking inside the lock window — fresh state
 *  3. Defense-in-depth: refuse if booking is no longer Submitted
 *  4. Re-check conflicts (Blueprint H.4 race mitigation — the slot may have
 *     been taken by another booking between submit and approve)
 *  5. Update the pending booking_approvals row → approved
 *  6. Update the booking → approved, clearing the Dec-03 hybrid pointer
 *  7. Record booking_status_histories transition
 *  8. Record activity_logs audit entry
 *
 * Everything wrapped in a single DB::transaction. On ANY failure the
 * entire write set is rolled back — a failed approval leaves the booking
 * exactly as it was (Submitted, pointer intact, approval row pending).
 *
 * The action is actor-agnostic: it trusts the caller to have authorized
 * the actor (BookingPolicy::approve gates the call site; Super Admin
 * override is handled by Gate::before in 2D-C). $actor is recorded as
 * acted_by_user_id for audit, distinct from the original assignee.
 *
 * @see BookingConflictService
 * @see BookingPolicy
 */
final class ApproveBookingAction
{
    public function __construct(
        private readonly BookingConflictService $conflictService,
    ) {}

    /**
     * Approve a submitted booking.
     *
     * @throws BookingConflictException When the slot was taken since submit
     * @throws DomainException When the booking is no longer in Submitted status
     */
    public function execute(Booking $booking, User $actor, ?string $notes = null): Booking
    {
        /** @var Booking $approved */
        $approved = DB::transaction(function () use ($booking, $actor, $notes): Booking {
            return $this->performApprove($booking, $actor, $notes);
        });

        // 2D-F: notify the requester AFTER the transaction commits, so a
        // notification never fires for a rolled-back approval. Looked up by
        // FK id (not the relation) to stay within larastan type resolution.
        User::findOrFail($approved->requester_user_id)
            ->notify(new BookingApprovedNotification($approved));

        return $approved;
    }

    private function performApprove(Booking $booking, User $actor, ?string $notes): Booking
    {
        // 1. Lock the room row
        /** @var Room $room */
        $room = Room::query()
            ->lockForUpdate()
            ->findOrFail($booking->room_id);

        // 2. Reload booking inside the lock — get fresh state
        /** @var Booking $booking */
        $booking = Booking::query()
            ->lockForUpdate()
            ->findOrFail($booking->id);

        // 3. Defense-in-depth: booking must still be Submitted.
        // status is cast to BookingStatus enum — compare against enum case.
        if ($booking->status !== BookingStatus::Submitted) {
            throw new DomainException(
                'Booking sudah tidak dalam status menunggu persetujuan.'
            );
        }

        // 4. Re-check conflicts (Blueprint H.4). Exclude this booking itself —
        // it is a locking-status row and would otherwise self-conflict.
        $this->conflictService->assertNoConflict(
            $room,
            $booking->starts_at,
            $booking->ends_at,
            $booking->id,
        );

        // 5. Update the pending approval row at the current step.
        /** @var BookingApproval $approvalRow */
        $approvalRow = $booking->approvals()
            ->where('sequence_no', $booking->current_approval_step)
            ->firstOrFail();

        $approvalRow->update([
            'status' => 'approved',
            'action_at' => Carbon::now(),
            'action_notes' => $notes,
            'acted_by_user_id' => $actor->id,
        ]);

        // 6. Update the booking — clear the Dec-03 hybrid pointer (terminal status).
        $booking->update([
            'status' => BookingStatus::Approved->value,
            'approved_at' => Carbon::now(),
            'current_approval_step' => null,
            'current_approver_user_id' => null,
            'updated_by_user_id' => $actor->id,
        ]);

        // 7. Status history
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => BookingStatus::Submitted->value,
            'to_status' => BookingStatus::Approved->value,
            'changed_by_user_id' => $actor->id,
            'changed_at' => Carbon::now(),
        ]);

        // 8. Audit log
        ActivityLog::create([
            'actor_user_id' => $actor->id,
            'module' => 'bookings',
            'event' => 'approve',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'description' => sprintf(
                'Booking %s disetujui oleh %s.',
                $booking->booking_code,
                $actor->name,
            ),
            'context' => [
                'room_id' => $room->id,
                'approval_step' => $approvalRow->sequence_no,
                'acted_by_user_id' => $actor->id,
            ],
        ]);

        return $booking->fresh(['room', 'requester', 'approvals']) ?? $booking;
    }
}
