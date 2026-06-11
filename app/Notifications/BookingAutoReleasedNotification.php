<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\ReleaseNoShowBookingAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Concerns\ConfigurableNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification to the requester when their approved booking is auto-released as
 * a no-show (nobody checked in within the grace window; the room is reclaimed).
 * Database (in-app inbox) + mail. Queued. Dispatched by
 * ReleaseNoShowBookingAction after commit.
 *
 * @see ReleaseNoShowBookingAction
 */
final class BookingAutoReleasedNotification extends Notification implements ShouldQueue
{
    use ConfigurableNotification;
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::BookingAutoReleased;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tz = (string) config('app.display_timezone', 'Asia/Jakarta');
        $name = $notifiable instanceof User ? $notifiable->name : 'Pengguna';
        $waktu = $this->booking->starts_at->copy()->setTimezone($tz)
            ->locale('id')->isoFormat('dddd, D MMMM Y [pukul] HH:mm');

        return (new MailMessage)
            ->subject('Reservasi Dilepas Otomatis: '.$this->booking->booking_code)
            ->greeting('Halo '.$name.',')
            ->line(sprintf(
                'Reservasi %s dilepas otomatis karena tidak ada check-in setelah waktu mulai (no-show), dan ruang telah dikembalikan.',
                $this->booking->booking_code,
            ))
            ->line('Subjek: '.$this->booking->subject)
            ->line('Ruang: '.($this->booking->room->name ?? '-'))
            ->line('Waktu: '.$waktu)
            ->action('Tinjau Reservasi', route('bookings.show', $this->booking));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::BookingAutoReleased->value,
            'booking_id' => $this->booking->id,
            'booking_code' => $this->booking->booking_code,
            'subject' => $this->booking->subject,
            'message' => sprintf(
                'Reservasi %s dilepas otomatis (no-show).',
                $this->booking->booking_code,
            ),
            'url' => route('bookings.show', $this->booking),
        ];
    }
}
