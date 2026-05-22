<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingSubmittedNotification;
use App\Services\ApprovalRoutingService;
use App\Services\BookingConflictService;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Submits an existing Draft booking — the Draft -> Submitted transition.
 *
 * Where SubmitBookingAction creates a brand-new booking, this action takes a
 * booking already persisted as Draft (a fresh draft, or one reverted from
 * Submitted by UpdateBookingAction per M3-Dec-1) and runs it through the
 * submit pipeline:
 *
 *  1. Lock the target room + booking rows (lockForUpdate) for race safety
 *  2. Guard: the booking must still be Draft
 *  3. Defense-in-depth: the room must still be active
 *  4. Re-check conflicts inside the lock window — a Draft never locked a
 *     slot, so another booking may have taken it (M3-Dec-12)
 *  5. Resolve approval routing for the booking's requester
 *  6. Transition the booking: status, hybrid pointer, re-snapshot the
 *     approval mode, stamp submitted_at
 *  7. Create the booking_approvals row when approval is required
 *  8. Record the Draft -> {status} history + activity_logs audit entry
 *
 * Everything is wrapped in a single DB::transaction; on any failure the
 * whole write set rolls back. The approver notification fires after commit.
 *
 * @see SubmitBookingAction
 * @see ApprovalRoutingService
 * @see UpdateBookingAction
 */
final class SubmitDraftAction
{
    public function __construct(
        private readonly BookingConflictService $conflictService,
        private readonly ApprovalRoutingService $routingService,
    ) {}

    /**
     * Submit the given Draft booking. $actor is the user triggering the
     * submit — the booking's owner, enforced by BookingPolicy::submit.
     *
     * Throws DomainException when the booking is not Draft or the room is
     * inactive, BookingConflictException when the slot is taken, and
     * ApprovalRoutingException when no approver can be resolved.
     */
    public function execute(Booking $booking, User $actor): Booking
    {
        /** @var Booking $submitted */
        $submitted = DB::transaction(function () use ($booking, $actor): Booking {
            return $this->performSubmit($booking, $actor);
        });

        // Notify the assigned approver — only when the booking resolved to
        // Submitted (auto-approved bookings have no approver). After the
        // transaction, by FK id.
        if ($submitted->status === BookingStatus::Submitted
            && $submitted->current_approver_user_id !== null) {
            User::findOrFail($submitted->current_approver_user_id)
                ->notify(new BookingSubmittedNotification($submitted));
        }

        return $submitted;
    }

    private function performSubmit(Booking $booking, User $actor): Booking
    {
        // 1. Lock the room and the booking rows.
        /** @var Room $room */
        $room = Room::query()
            ->lockForUpdate()
            ->findOrFail($booking->room_id);

        /** @var Booking $locked */
        $locked = Booking::query()
            ->lockForUpdate()
            ->findOrFail($booking->id);

        // 2. The booking must still be Draft.
        if ($locked->status !== BookingStatus::Draft) {
            throw new DomainException(
                'Hanya reservasi berstatus draft yang dapat diajukan.'
            );
        }

        // 3. Defense-in-depth: the room must still be active.
        if (! $room->is_active || $room->status !== RoomStatus::Active) {
            throw new DomainException(
                'Ruangan tidak tersedia untuk pemesanan saat ini.'
            );
        }

        // 4. Re-check conflicts inside the lock window. A Draft never locked
        // the slot, so it may have been taken since the draft was saved.
        // The draft itself is excluded from the check.
        $this->conflictService->assertNoConflict(
            $room,
            $locked->starts_at,
            $locked->ends_at,
            $locked->id,
        );

        // 5. Resolve approval routing for the booking's requester (not the
        // actor) — the unit approver follows whoever owns the booking.
        $requester = User::query()->findOrFail($locked->requester_user_id);
        $resolution = $this->routingService->resolve($requester, $room->approval_mode);

        // 6. Transition the booking. Re-snapshot the approval mode — the
        // room's mode may have changed since the draft was created.
        //
        // current_approval_step / the approval row's sequence_no is the next
        // free per-booking ordinal, NOT a hardcoded 1. A booking reverted
        // from Submitted (M3-Dec-1, UpdateBookingAction) keeps its prior
        // approval row as cancelled, and booking_approvals carries a
        // unique(booking_id, sequence_no) constraint — a re-submit reusing
        // sequence_no 1 would collide. A fresh draft has no rows, so
        // (int) null + 1 = 1; a reverted-then-resubmitted draft advances to 2.
        $approvalRequired = $resolution['approver_user_id'] !== null;
        $nextStep = (int) $locked->approvals()->max('sequence_no') + 1;

        $locked->update([
            'status' => $resolution['status']->value,
            'approval_mode_snapshot' => $room->approval_mode->value,
            'current_approval_step' => $approvalRequired ? $nextStep : null,
            'current_approver_user_id' => $resolution['approver_user_id'],
            'submitted_at' => Carbon::now(),
            'approved_at' => $resolution['approved_at'],
            'updated_by_user_id' => $actor->id,
        ]);

        // 7. Create the approval row when approval is required.
        if ($approvalRequired) {
            BookingApproval::create([
                'booking_id' => $locked->id,
                'sequence_no' => $nextStep,
                'approver_user_id' => $resolution['approver_user_id'],
                'status' => 'pending',
            ]);
        }

        // 8. Status history + audit log.
        BookingStatusHistory::create([
            'booking_id' => $locked->id,
            'from_status' => BookingStatus::Draft->value,
            'to_status' => $resolution['status']->value,
            'changed_by_user_id' => $actor->id,
            'changed_at' => Carbon::now(),
        ]);

        ActivityLog::create([
            'actor_user_id' => $actor->id,
            'module' => 'bookings',
            'event' => 'submit',
            'subject_type' => Booking::class,
            'subject_id' => $locked->id,
            'description' => sprintf(
                'Booking %s diajukan dari draft dengan status %s.',
                $locked->booking_code,
                $resolution['status']->value,
            ),
            'context' => [
                'approval_mode' => $room->approval_mode->value,
                'room_id' => $room->id,
                'from_status' => BookingStatus::Draft->value,
            ],
        ]);

        return $locked->fresh(['room', 'requester', 'currentApprover', 'approvals']) ?? $locked;
    }
}
