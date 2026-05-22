<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\User;
use App\Notifications\BookingRejectedNotification;
use App\Policies\BookingPolicy;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Rejects a Submitted booking through the full rejection pipeline:
 *
 *  1. Validate the rejection reason is non-empty (2D-B-Dec-3)
 *  2. Lock the booking row (lockForUpdate) for fresh state + race safety
 *     against a parallel approve/cancel on the same booking
 *  3. Defense-in-depth: refuse if booking is no longer Submitted
 *  4. Update the pending booking_approvals row → rejected
 *  5. Update the booking → rejected, clearing the Dec-03 hybrid pointer
 *  6. Record booking_status_histories transition
 *  7. Record activity_logs audit entry
 *
 * Everything wrapped in a single DB::transaction. On ANY failure the
 * entire write set is rolled back.
 *
 * Unlike ApproveBookingAction, this action does NOT re-check conflicts
 * and does NOT lock the room row (2D-B-Dec-1, 2D-B-Dec-2). Rejection
 * RELEASES a slot — 'rejected' is not a locking status — so it cannot
 * create a conflict. There is no race on room availability to guard.
 *
 * The rejection reason is required and is written to BOTH
 * bookings.rejection_reason (the canonical field) and
 * booking_approvals.action_notes (the per-step audit trail) — 2D-B-Dec-4.
 *
 * The action is actor-agnostic: it records $actor as acted_by_user_id
 * but does not authorize. BookingPolicy::reject gates the call site;
 * Super Admin override arrives in 2D-C via Gate::before.
 *
 * @see BookingPolicy
 */
final class RejectBookingAction
{
    /**
     * Reject a submitted booking.
     *
     * @throws InvalidArgumentException When the rejection reason is empty
     * @throws DomainException When the booking is no longer in Submitted status
     */
    public function execute(Booking $booking, User $actor, string $reason): Booking
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException(
                'Alasan penolakan wajib diisi.'
            );
        }

        /** @var Booking $rejected */
        $rejected = DB::transaction(function () use ($booking, $actor, $reason): Booking {
            return $this->performReject($booking, $actor, $reason);
        });

        // 2D-F: notify the requester after the transaction commits (see Approve).
        User::findOrFail($rejected->requester_user_id)
            ->notify(new BookingRejectedNotification($rejected));

        return $rejected;
    }

    private function performReject(Booking $booking, User $actor, string $reason): Booking
    {
        // 1. Lock + reload the booking row inside the transaction.
        /** @var Booking $booking */
        $booking = Booking::query()
            ->lockForUpdate()
            ->findOrFail($booking->id);

        // 2. Defense-in-depth: booking must still be Submitted.
        // status is cast to BookingStatus enum — compare against enum case.
        if ($booking->status !== BookingStatus::Submitted) {
            throw new DomainException(
                'Booking sudah tidak dalam status menunggu persetujuan.'
            );
        }

        // 3. Update the pending approval row at the current step.
        /** @var BookingApproval $approvalRow */
        $approvalRow = $booking->approvals()
            ->where('sequence_no', $booking->current_approval_step)
            ->firstOrFail();

        $approvalRow->update([
            'status' => 'rejected',
            'action_at' => Carbon::now(),
            'action_notes' => $reason,
            'acted_by_user_id' => $actor->id,
        ]);

        // 4. Update the booking — rejected is terminal; clear the Dec-03
        // hybrid pointer. The reason lands in the dedicated column.
        $booking->update([
            'status' => BookingStatus::Rejected->value,
            'rejected_at' => Carbon::now(),
            'rejection_reason' => $reason,
            'current_approval_step' => null,
            'current_approver_user_id' => null,
            'updated_by_user_id' => $actor->id,
        ]);

        // 5. Status history
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => BookingStatus::Submitted->value,
            'to_status' => BookingStatus::Rejected->value,
            'changed_by_user_id' => $actor->id,
            'change_reason' => $reason,
            'changed_at' => Carbon::now(),
        ]);

        // 6. Audit log
        ActivityLog::create([
            'actor_user_id' => $actor->id,
            'module' => 'bookings',
            'event' => 'reject',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'description' => sprintf(
                'Booking %s ditolak oleh %s.',
                $booking->booking_code,
                $actor->name,
            ),
            'context' => [
                'approval_step' => $approvalRow->sequence_no,
                'acted_by_user_id' => $actor->id,
                'rejection_reason' => $reason,
            ],
        ]);

        return $booking->fresh(['room', 'requester', 'approvals']) ?? $booking;
    }
}
