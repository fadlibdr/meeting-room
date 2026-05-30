<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Policies\BookingPolicy;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Cancels a booking through the full cancellation pipeline.
 *
 * Valid source statuses: Draft, Submitted, Approved (cf.
 * BookingPolicy::cancel / isCancellableStatus). Rejected, Cancelled and
 * Completed cannot be cancelled.
 *
 * Pipeline (single DB::transaction — any failure rolls back the whole set):
 *
 *  1. Lock the booking row (lockForUpdate) for fresh state + race safety
 *     against a parallel approve/reject/cancel on the same booking.
 *  2. Defense-in-depth: refuse if the booking is no longer cancellable.
 *  3. Reason guard: a cancellation reason is REQUIRED when the booking is
 *     Approved (Blueprint H.5); optional for Draft / Submitted.
 *  4. Submitted only: set the pending booking_approvals row -> cancelled.
 *     Draft has no approval row; an Approved booking's rows are already
 *     terminal 'approved' and are left untouched as history.
 *  5. Update the booking -> cancelled, clearing the Dec-03 hybrid pointer.
 *     The pointer is already null for Draft / Approved, so the null-write
 *     is a harmless no-op there and preserves the IntegrityTest invariant.
 *  6. Record the booking_status_histories transition.
 *  7. Record the activity_logs audit entry.
 *
 * Unlike ApproveBookingAction this action runs NO conflict re-check and
 * does NOT lock the room row — cancellation RELEASES a slot ('cancelled'
 * is not a locking status, BookingStatus::locksSlot()), so it cannot
 * create a conflict. It still locks the booking row for same-booking
 * race safety (cf. RejectBookingAction).
 *
 * Notification (M3-Dec-3): a BookingCancelledNotification is sent to the
 * booking's assigned approver AFTER the transaction commits — but only
 * when (a) the caller did not pass notify: false and (b) the booking was
 * not a Draft (a Draft was never visible to an approver). The reschedule
 * pipeline passes notify: false so the approver receives a single signal
 * (booking B's submitted notification) instead of two.
 *
 * The action is actor-agnostic: it records $actor as the change author
 * but does not authorize. BookingPolicy::cancel gates the call site.
 *
 * @see BookingPolicy
 */
final class CancelBookingAction
{
    /**
     * Cancel a booking.
     *
     * @param  bool  $notify  Whether to dispatch BookingCancelledNotification
     *                        (false for the reschedule pipeline — M3-Dec-3).
     *
     * @throws DomainException When the booking is not in a cancellable status
     * @throws InvalidArgumentException When cancelling an Approved booking
     *                                  without a reason
     */
    public function execute(
        Booking $booking,
        User $actor,
        ?string $reason = null,
        bool $notify = true,
    ): Booking {
        $reason = $reason !== null ? trim($reason) : null;

        if ($reason === '') {
            $reason = null;
        }

        /** @var array{booking: Booking, from_status: BookingStatus} $result */
        $result = DB::transaction(
            fn (): array => $this->performCancel($booking, $actor, $reason),
        );

        $cancelled = $result['booking'];
        $fromStatus = $result['from_status'];

        // M3-Dec-3: notify the assigned approver after commit — unless
        // suppressed, or the booking was a Draft (no approver ever saw it).
        if ($notify && $fromStatus !== BookingStatus::Draft) {
            /** @var BookingApproval|null $approval */
            $approval = $cancelled->approvals()
                ->orderBy('sequence_no')
                ->first();

            if ($approval !== null) {
                User::findOrFail($approval->approver_user_id)
                    ->notify(new BookingCancelledNotification($cancelled));
            }
        }

        return $cancelled;
    }

    /**
     * @return array{booking: Booking, from_status: BookingStatus}
     */
    private function performCancel(Booking $booking, User $actor, ?string $reason): array
    {
        // 1. Lock + reload the booking row inside the transaction.
        /** @var Booking $booking */
        $booking = Booking::query()
            ->lockForUpdate()
            ->findOrFail($booking->id);

        // 2. Defense-in-depth: booking must still be cancellable.
        $fromStatus = $booking->status;

        if (! in_array($fromStatus, [
            BookingStatus::Draft,
            BookingStatus::Submitted,
            BookingStatus::Approved,
        ], strict: true)) {
            throw new DomainException(
                'Booking sudah tidak dapat dibatalkan.'
            );
        }

        // 3. Reason guard: required for an Approved booking (Blueprint H.5).
        if ($fromStatus === BookingStatus::Approved && $reason === null) {
            throw new InvalidArgumentException(
                'Alasan pembatalan wajib diisi untuk booking yang sudah disetujui.'
            );
        }

        // 4. Submitted only: cancel the pending approval row. Draft has no
        // row; an Approved booking's rows stay as 'approved' history.
        if ($fromStatus === BookingStatus::Submitted) {
            /** @var BookingApproval $approvalRow */
            $approvalRow = $booking->approvals()
                ->where('sequence_no', $booking->current_approval_step)
                ->firstOrFail();

            $approvalRow->update([
                'status' => 'cancelled',
                'action_at' => Carbon::now(),
                'action_notes' => $reason,
                'acted_by_user_id' => $actor->id,
            ]);
        }

        // 5. Update the booking — cancelled is terminal; clear the Dec-03
        // hybrid pointer (no-op when already null for Draft / Approved).
        $booking->update([
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => Carbon::now(),
            'cancellation_reason' => $reason,
            'current_approval_step' => null,
            'current_approver_user_id' => null,
            'updated_by_user_id' => $actor->id,
        ]);

        // 6. Status history
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $fromStatus->value,
            'to_status' => BookingStatus::Cancelled->value,
            'changed_by_user_id' => $actor->id,
            'change_reason' => $reason,
            'changed_at' => Carbon::now(),
        ]);

        // 7. Audit log
        ActivityLog::create([
            'actor_user_id' => $actor->id,
            'module' => 'bookings',
            'event' => 'cancel',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'description' => sprintf(
                'Booking %s dibatalkan oleh %s.',
                $booking->booking_code,
                $actor->name,
            ),
            'context' => [
                'from_status' => $fromStatus->value,
                'cancellation_reason' => $reason,
                'acted_by_user_id' => $actor->id,
            ],
        ]);

        $fresh = $booking->fresh(['room', 'requester', 'approvals']);

        return [
            'booking' => $fresh ?? $booking,
            'from_status' => $fromStatus,
        ];
    }
}
