<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\CancelBookingAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * In-app notification sent to a booking's assigned approver when the
 * booking is cancelled. Database channel only. Dispatched by
 * CancelBookingAction after its transaction commits — NOT for Draft
 * cancellations (never visible to an approver) and NOT when the
 * reschedule pipeline suppresses it (M3-Dec-3).
 *
 * @see CancelBookingAction
 */
final class BookingCancelledNotification extends Notification
{
    public function __construct(
        private readonly Booking $booking,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::BookingCancelled->value,
            'booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'subject' => $this->booking->subject,
            'message' => sprintf(
                'Reservasi %s dibatalkan.',
                $this->booking->booking_code,
            ),
            'reason' => $this->booking->cancellation_reason,
            'url' => route('bookings.show', $this->booking->id),
        ];
    }
}
