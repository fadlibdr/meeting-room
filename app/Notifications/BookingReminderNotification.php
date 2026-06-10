<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Console\Commands\SendBookingReminders;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Concerns\ConfigurableNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Reminder to a booking's requester that their approved booking is starting
 * soon. Database (in-app inbox) + mail. Queued. Dispatched by the
 * bookings:send-reminders scheduled command.
 *
 * @see SendBookingReminders
 */
final class BookingReminderNotification extends Notification implements ShouldQueue
{
    use ConfigurableNotification;
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::BookingReminder;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tz = (string) config('app.display_timezone', 'Asia/Jakarta');
        $name = $notifiable instanceof User ? $notifiable->name : 'Pengguna';
        $waktu = $this->booking->starts_at->copy()->setTimezone($tz)
            ->locale('id')->isoFormat('dddd, D MMMM Y [pukul] HH:mm');

        return (new MailMessage)
            ->subject('Pengingat Reservasi: '.$this->booking->booking_code)
            ->greeting('Halo '.$name.',')
            ->line(sprintf('Pengingat: reservasi %s akan dimulai pada %s.', $this->booking->booking_code, $waktu))
            ->line('Ruang: '.($this->booking->room->name ?? '-'))
            ->action('Lihat Reservasi', route('bookings.show', $this->booking->id));
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
            'resource_id' => $this->booking->resource_id,
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
