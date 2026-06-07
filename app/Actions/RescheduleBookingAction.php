<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingSubmittedNotification;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Reschedules an Approved booking (M3-Dec-3 / Blueprint H.6).
 *
 * Reschedule is NOT an in-place edit. It is "cancel + create new": the
 * original Approved booking A is cancelled and a fresh booking B is
 * submitted, linked back via B.rescheduled_from_booking_id = A.id. Silently
 * mutating an approved booking's times would erase what was actually
 * approved; cancel-and-relink keeps the audit trail honest.
 *
 * Both steps run inside ONE DB::transaction. CancelBookingAction and
 * SubmitBookingAction each open their own transaction; nested inside the
 * outer one those become savepoints, so the reschedule is atomic. If
 * submitting B fails -- most commonly because the new slot is already taken
 * -- A's cancellation rolls back with it and the user keeps the original
 * Approved booking. There is never a window where A is gone and B does not
 * exist.
 *
 * Ordering: A is cancelled BEFORE B is submitted. Cancelling A releases its
 * slot ('cancelled' is not a locking status), so B may reuse A's old room
 * at an overlapping time without A blocking itself.
 *
 * Notifications (M3-Dec-3): both sub-actions are invoked with notify:false.
 * A's cancellation notification is suppressed entirely; B's submitted
 * notification is dispatched once by this action AFTER the transaction
 * commits, so the approver receives a single coherent signal.
 *
 * Actor-agnostic: records $actor as the change author but does not
 * authorize. BookingPolicy::reschedule (M3-E) gates the call site.
 *
 * @see CancelBookingAction
 * @see SubmitBookingAction
 */
final class RescheduleBookingAction
{
    public function __construct(
        private readonly CancelBookingAction $cancelAction,
        private readonly SubmitBookingAction $submitAction,
    ) {}

    /**
     * Reschedule the Approved booking $original onto $newData, returning the
     * newly created booking B.
     *
     * @param  array{resource_id?: int, room_id?: int, subject: string, agenda?: ?string, attendee_count: int, starts_at: string, ends_at: string}  $newData
     *
     * @throws DomainException When $original is not Approved
     */
    public function execute(Booking $original, User $actor, array $newData): Booking
    {
        /** @var Booking $newBooking */
        $newBooking = DB::transaction(
            fn (): Booking => $this->performReschedule($original, $actor, $newData),
        );

        // M3-Dec-3: B's submitted notification is the single signal, fired
        // once after commit. A's cancel notification was suppressed.
        if ($newBooking->status === BookingStatus::Submitted
            && $newBooking->current_approver_user_id !== null) {
            User::findOrFail($newBooking->current_approver_user_id)
                ->notify(new BookingSubmittedNotification($newBooking));
        }

        return $newBooking;
    }

    /**
     * @param  array{resource_id?: int, room_id?: int, subject: string, agenda?: ?string, attendee_count: int, starts_at: string, ends_at: string}  $newData
     */
    private function performReschedule(Booking $original, User $actor, array $newData): Booking
    {
        // 1. Lock + reload the original booking inside the transaction.
        /** @var Booking $a */
        $a = Booking::query()
            ->lockForUpdate()
            ->findOrFail($original->id);

        // 2. Reschedule applies only to Approved bookings (Blueprint H.6).
        if ($a->status !== BookingStatus::Approved) {
            throw new DomainException(
                'Hanya reservasi yang sudah disetujui yang dapat dijadwalkan ulang.'
            );
        }

        // 3. Cancel A -- notification suppressed (M3-Dec-3). A is Approved,
        // so CancelBookingAction requires a reason; supply a system reason.
        $this->cancelAction->execute(
            $a,
            $actor,
            'Reservasi dijadwalkan ulang ke jadwal baru.',
            notify: false,
        );

        // 4. Submit B. A is now Cancelled and no longer locks its slot, so B
        // may reuse A's room/time without a self-conflict. Notification
        // suppressed here; execute() fires it once after commit.
        $newBooking = $this->submitAction->execute($actor, $newData, notify: false);

        // 5. Link B -> A (M3-Dec-3). rescheduled_from_booking_id is fillable.
        $newBooking->update(['rescheduled_from_booking_id' => $a->id]);

        // 6. A first-class 'reschedule' audit entry tying A and B together,
        // on top of A's 'cancel' and B's 'submit' log entries.
        ActivityLog::create([
            'actor_user_id' => $actor->id,
            'module' => 'bookings',
            'event' => 'reschedule',
            'subject_type' => Booking::class,
            'subject_id' => $newBooking->id,
            'description' => sprintf(
                'Booking %s dijadwalkan ulang dari %s.',
                $newBooking->booking_code,
                $a->booking_code,
            ),
            'context' => [
                'rescheduled_from_booking_id' => $a->id,
                'original_booking_code' => $a->booking_code,
            ],
        ]);

        return $newBooking->fresh(['room', 'requester', 'currentApprover', 'approvals'])
            ?? $newBooking;
    }
}
