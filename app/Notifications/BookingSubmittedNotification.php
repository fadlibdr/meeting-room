<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\SubmitBookingAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * In-app notification sent to the assigned approver when a booking enters
 * Submitted status and awaits their decision. Database channel only — a
 * synchronous insert into `notifications`. Dispatched by SubmitBookingAction
 * after its transaction commits, and only when an approver exists.
 *
 * @see SubmitBookingAction
 */
final class BookingSubmittedNotification extends Notification
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
            'type' => NotificationType::BookingSubmitted->value,
            'booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'subject' => $this->booking->subject,
            'message' => sprintf(
                'Reservasi %s menunggu persetujuan Anda.',
                $this->booking->booking_code,
            ),
            'url' => route('bookings.show', $this->booking->id),
        ];
    }
}
