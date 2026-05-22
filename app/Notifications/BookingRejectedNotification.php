<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\RejectBookingAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * In-app notification sent to the requester when their booking is rejected.
 * Database channel only. Dispatched by RejectBookingAction after its
 * transaction commits. Carries the rejection reason in the payload.
 *
 * @see RejectBookingAction
 */
final class BookingRejectedNotification extends Notification
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
            'type' => NotificationType::BookingRejected->value,
            'booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'subject' => $this->booking->subject,
            'message' => sprintf(
                'Reservasi %s ditolak.',
                $this->booking->booking_code,
            ),
            'reason' => $this->booking->rejection_reason,
            'url' => route('bookings.show', $this->booking->id),
        ];
    }
}
