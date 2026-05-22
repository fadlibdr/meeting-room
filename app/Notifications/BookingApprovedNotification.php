<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\ApproveBookingAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * In-app notification sent to the requester when their booking is approved.
 * Database channel only. Dispatched by ApproveBookingAction after its
 * transaction commits. Auto-approval at submit does NOT fire this (2D-F-Dec-5).
 *
 * @see ApproveBookingAction
 */
final class BookingApprovedNotification extends Notification
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
            'type' => NotificationType::BookingApproved->value,
            'booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'subject' => $this->booking->subject,
            'message' => sprintf(
                'Reservasi %s telah disetujui.',
                $this->booking->booking_code,
            ),
            'url' => route('bookings.show', $this->booking->id),
        ];
    }
}
