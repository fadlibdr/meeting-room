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
use App\Services\BookingConflictService;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applies a requester's edits to a Draft or Submitted booking (M3-C / M3-Dec-1).
 *
 * Two paths, one action:
 *
 *  - Editing a DRAFT booking is a plain in-place save. No status change and
 *    no status-history row (a Draft -> Draft edit is not a transition).
 *
 *  - Editing a SUBMITTED booking REVERTS it to Draft (M3-Dec-1): scheduling
 *    data must never be mutated under a live approver. The pending
 *    booking_approvals row is cancelled, the Dec-03 hybrid pointer is nulled,
 *    submitted_at is cleared, and a Submitted -> Draft booking_status_histories
 *    row is recorded. The requester re-submits afterwards; the conflict check
 *    and approval routing run fresh then.
 *
 * Pipeline (mirrors ApproveBookingAction's race-safe shape):
 *  1. Lock the target room row (the edit may move the booking to a new room)
 *  2. Reload the booking inside the lock — fresh state
 *  3. Defense-in-depth: refuse unless the booking is Draft or Submitted
 *  4. Re-check conflicts for the new slot, excluding the booking itself
 *     (a Submitted booking is a locking-status row and would self-conflict)
 *  5. Cancel the pending approval row (Submitted path only)
 *  6. Apply the field changes — and, on the Submitted path, the revert
 *  7. Record the Submitted -> Draft status history (Submitted path only)
 *  8. Record an activity_logs audit entry
 *
 * Everything is wrapped in a single DB::transaction; any failure rolls back
 * the entire write set. No notification is dispatched — an edit-revert is a
 * transient state (the booking returns to Submitted on re-submission), so
 * notifying the approver "withdrawn" then "submitted" seconds apart would be
 * noise. The fresh BookingSubmittedNotification at re-submission is the single
 * approver-facing signal.
 *
 * Actor-agnostic: it trusts the caller (BookingPolicy::update via BookingForm)
 * to have authorized the actor. $actor is recorded for audit.
 *
 * @see ApproveBookingAction
 * @see BookingConflictService
 */
final class UpdateBookingAction
{
    public function __construct(
        private readonly BookingConflictService $conflictService,
    ) {}

    /**
     * Apply edits to a Draft or Submitted booking.
     *
     * @param  array{room_id: int, subject: string, agenda: string|null, attendee_count: int, starts_at: string, ends_at: string}  $data
     *
     * @throws BookingConflictException When the new slot overlaps another booking/block
     * @throws DomainException When the booking is not in an editable status
     */
    public function execute(Booking $booking, User $actor, array $data): Booking
    {
        /** @var Booking $updated */
        $updated = DB::transaction(function () use ($booking, $actor, $data): Booking {
            return $this->performUpdate($booking, $actor, $data);
        });

        return $updated;
    }

    /**
     * @param  array{room_id: int, subject: string, agenda: string|null, attendee_count: int, starts_at: string, ends_at: string}  $data
     */
    private function performUpdate(Booking $booking, User $actor, array $data): Booking
    {
        // 1. Lock the target room (the edit may move the booking to a new room).
        /** @var Room $room */
        $room = Room::query()
            ->lockForUpdate()
            ->findOrFail($data['room_id']);

        // 2. Reload the booking inside the lock — fresh state.
        /** @var Booking $booking */
        $booking = Booking::query()
            ->lockForUpdate()
            ->findOrFail($booking->id);

        // 3. Defense-in-depth: only Draft / Submitted bookings are editable.
        if (! in_array($booking->status, [BookingStatus::Draft, BookingStatus::Submitted], true)) {
            throw new DomainException('Booking ini tidak dapat diubah.');
        }

        // 4. Re-check conflicts for the new slot. Exclude this booking itself —
        //    a Submitted booking is a locking-status row and would self-conflict.
        $startsAt = Carbon::parse($data['starts_at'])->utc();
        $endsAt = Carbon::parse($data['ends_at'])->utc();
        $this->conflictService->assertNoConflict($room, $startsAt, $endsAt, $booking->id);

        $wasSubmitted = $booking->status === BookingStatus::Submitted;

        // 5. Submitted path: cancel the pending approval row BEFORE the pointer
        //    is nulled (M3-Dec-1 — same revert mechanic as CancelBookingAction).
        if ($wasSubmitted) {
            /** @var BookingApproval|null $approvalRow */
            $approvalRow = $booking->approvals()
                ->where('sequence_no', $booking->current_approval_step)
                ->first();

            $approvalRow?->update([
                'status' => 'cancelled',
                'action_at' => Carbon::now(),
            ]);
        }

        // 6. Apply the field changes — plus the revert, on the Submitted path.
        $attributes = [
            'room_id' => $room->id,
            'subject' => $data['subject'],
            'agenda' => $data['agenda'] ?? null,
            'attendee_count' => $data['attendee_count'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'updated_by_user_id' => $actor->id,
        ];

        if ($wasSubmitted) {
            $attributes['status'] = BookingStatus::Draft->value;
            $attributes['current_approval_step'] = null;
            $attributes['current_approver_user_id'] = null;
            $attributes['submitted_at'] = null;
        }

        $booking->update($attributes);

        // 7. Status history — only on the Submitted -> Draft transition.
        if ($wasSubmitted) {
            BookingStatusHistory::create([
                'booking_id' => $booking->id,
                'from_status' => BookingStatus::Submitted->value,
                'to_status' => BookingStatus::Draft->value,
                'changed_by_user_id' => $actor->id,
                'changed_at' => Carbon::now(),
                'change_reason' => 'Booking diubah oleh pemesan; persetujuan sebelumnya dibatalkan dan booking perlu diajukan ulang.',
            ]);
        }

        // 8. Audit log.
        ActivityLog::create([
            'actor_user_id' => $actor->id,
            'module' => 'bookings',
            'event' => 'update',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'description' => sprintf(
                'Booking %s diubah oleh %s.',
                $booking->booking_code,
                $actor->name,
            ),
            'context' => [
                'room_id' => $room->id,
                'reverted_to_draft' => $wasSubmitted,
            ],
        ]);

        return $booking->fresh(['room', 'requester', 'approvals']) ?? $booking;
    }
}
