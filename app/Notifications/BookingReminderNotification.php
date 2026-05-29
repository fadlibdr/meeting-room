<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Console\Commands\SendBookingReminders;
use App\Enums\NotificationType;
use App\Models\Booking;
use Illuminate\Notifications\Notification;

/**
 * In-app reminder to a booking's requester that their approved booking is
 * starting soon. Database channel only; dispatched by the
 * bookings:send-reminders scheduled command.
 *
 * @see SendBookingReminders
 */
final class BookingReminderNotification extends Notification
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
        $tz = (string) config('app.display_timezone', 'Asia/Jakarta');
        $start = $this->booking->starts_at->copy()->setTimezone($tz);

        return [
            'type' => NotificationType::BookingReminder->value,
            'booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'room_id' => $this->booking->room_id,
            'starts_at' => $this->booking->starts_at->toIso8601String(),
            'message' => sprintf(
                'Pengingat: reservasi %s akan dimulai pada %s.',
                $this->booking->booking_code,
                $start->locale('id')->isoFormat('dddd, D MMMM Y [pukul] HH:mm'),
            ),
            'url' => route('bookings.show', $this->booking->id),
        ];
    }
}
