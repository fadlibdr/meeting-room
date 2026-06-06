<?php

declare(strict_types=1);

namespace App\Actions;

use App\Console\Commands\AutoReleaseBookings;
use App\Enums\BookingStatus;
use App\Enums\WebhookEvent;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingStatusHistory;
use App\Models\User;
use App\Notifications\BookingAutoReleasedNotification;
use App\Services\WebhookDispatcher;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Stage 3 A.1 — releases a no-show booking (system action, NULL actor).
 *
 * Mirrors the cancel pipeline (CancelBookingAction) but is driven by the
 * scheduler, not a user: it sets the booking Cancelled, stamps `released_at`
 * (the no-show signal that distinguishes it from a manual cancel), and notifies
 * the requester. The audit/history actor columns are nullable, so a null actor
 * is recorded for system provenance.
 *
 * Idempotent + defensive: only an Approved booking that has not been checked in
 * and not already released is eligible; anything else is returned untouched.
 * Approved bookings carry no pending approval row (their rows are terminal
 * 'approved'), so — unlike a Submitted cancel — there is no approval row to flip.
 *
 * @see CancelBookingAction
 * @see AutoReleaseBookings
 */
final class ReleaseNoShowBookingAction
{
    public const REASON = 'Dilepas otomatis: tidak ada check-in dalam batas toleransi (no-show).';

    public function execute(Booking $booking): Booking
    {
        /** @var array{booking: Booking, released: bool} $result */
        $result = DB::transaction(fn (): array => $this->performRelease($booking));

        $released = $result['booking'];

        if ($result['released']) {
            User::find($released->requester_user_id)
                ?->notify(new BookingAutoReleasedNotification($released));
            app(WebhookDispatcher::class)->dispatch(WebhookEvent::BookingAutoReleased, $released);
        }

        return $released;
    }

    /**
     * @return array{booking: Booking, released: bool}
     */
    private function performRelease(Booking $booking): array
    {
        // Lock + reload for fresh state and race safety against a parallel
        // check-in / cancel on the same booking.
        /** @var Booking $booking */
        $booking = Booking::query()->lockForUpdate()->findOrFail($booking->id);

        // Idempotency + defense-in-depth: a check-in or a prior release (or any
        // non-Approved status) makes the booking ineligible — leave it untouched.
        if ($booking->status !== BookingStatus::Approved
            || $booking->checked_in_at !== null
            || $booking->released_at !== null) {
            return ['booking' => $booking, 'released' => false];
        }

        $now = Carbon::now();

        // Cancelled is non-locking, so releasing the slot cannot create a
        // conflict; clear the Dec-03 pointer (already null for Approved — no-op).
        $booking->update([
            'status' => BookingStatus::Cancelled->value,
            'cancelled_at' => $now,
            'released_at' => $now,
            'cancellation_reason' => self::REASON,
            'current_approval_step' => null,
            'current_approver_user_id' => null,
        ]);

        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => BookingStatus::Approved->value,
            'to_status' => BookingStatus::Cancelled->value,
            'changed_by_user_id' => null,
            'change_reason' => self::REASON,
            'changed_at' => $now,
        ]);

        ActivityLog::create([
            'actor_user_id' => null,
            'module' => 'bookings',
            'event' => 'auto-release',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'description' => sprintf(
                'Booking %s dilepas otomatis oleh sistem (no-show).',
                $booking->booking_code,
            ),
            'context' => [
                'from_status' => BookingStatus::Approved->value,
                'reason' => self::REASON,
            ],
        ]);

        $fresh = $booking->fresh(['room', 'requester']);

        return ['booking' => $fresh ?? $booking, 'released' => true];
    }
}
