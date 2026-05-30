<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingAttachment;
use App\Models\User;
use App\Policies\BookingPolicy;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Hard-deletes a Draft booking (M3-F, M3-Dec-4).
 *
 * Draft-only. A Draft is structurally simple: it never holds
 * booking_approvals rows (those appear at submit) and is never the
 * target of a rescheduled_from_booking_id pointer (a reschedule source
 * is always Approved). The DB cascades booking_status_histories and
 * booking_attachments (both ON DELETE CASCADE), so the child ROWS need
 * no manual cleanup. The attachment FILES on the private disk are a
 * separate matter — a DB cascade never touches the filesystem — so they
 * are captured before the delete and purged from storage AFTER the
 * transaction commits (a rolled-back delete must not remove files).
 *
 * The deletion is HARD: the bookings row is removed for good. It is
 * still auditable — an activity_logs row is written inside the same
 * transaction, BEFORE the delete. activity_logs has no FK to bookings,
 * so that audit row survives; its subject_id then points at an absent
 * row, which is expected for a hard delete.
 *
 * Unlike CancelBookingAction this writes NO booking_status_histories
 * row (delete is not a status transition, and any such row would be
 * cascade-deleted with the booking anyway) and dispatches NO
 * notification (a Draft was never visible to an approver).
 *
 * Actor-agnostic: it records $actor as the audit author but does not
 * authorize. BookingPolicy::delete gates the call site.
 *
 * @see BookingPolicy
 */
final class DeleteBookingAction
{
    /**
     * Permanently delete a Draft booking.
     *
     * @throws DomainException When the booking is not a Draft.
     */
    public function execute(Booking $booking, User $actor): void
    {
        // Capture attachment files up front so they can be purged from storage
        // after the delete commits — a DB cascade removes the rows, not the
        // files on disk.
        $files = [];
        foreach ($booking->attachments()->get() as $attachment) {
            /** @var BookingAttachment $attachment */
            $files[] = ['disk' => $attachment->disk, 'path' => $attachment->path];
        }

        DB::transaction(function () use ($booking, $actor): void {
            // 1. Lock + reload the booking row inside the transaction —
            // fresh state + race safety against a parallel submit/cancel.
            /** @var Booking $locked */
            $locked = Booking::query()
                ->lockForUpdate()
                ->findOrFail($booking->id);

            // 2. Defense-in-depth: only a Draft may be hard-deleted.
            if ($locked->status !== BookingStatus::Draft) {
                throw new DomainException(
                    'Hanya reservasi berstatus draf yang dapat dihapus permanen.'
                );
            }

            // 3. Audit BEFORE the delete — activity_logs has no FK to
            // bookings, so this row survives the delete (M3-Dec-4).
            ActivityLog::create([
                'actor_user_id' => $actor->id,
                'module' => 'bookings',
                'event' => 'delete',
                'subject_type' => Booking::class,
                'subject_id' => $locked->id,
                'description' => sprintf(
                    'Reservasi draf %s dihapus permanen oleh %s.',
                    $locked->booking_code,
                    $actor->name,
                ),
                'context' => [
                    'booking_code' => $locked->booking_code,
                    'from_status' => $locked->status->value,
                    'acted_by_user_id' => $actor->id,
                ],
            ]);

            // 4. Hard delete. The DB cascades booking_status_histories
            // and booking_attachments rows.
            $locked->delete();
        });

        // 5. Post-commit: the rows are gone via cascade; their files are not
        // (a DB cascade never touches storage). Remove them now — after the
        // commit, so a rolled-back delete leaves files intact.
        foreach ($files as $file) {
            Storage::disk($file['disk'])->delete($file['path']);
        }
    }
}
