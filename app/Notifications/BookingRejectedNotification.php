<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\RejectBookingAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Concerns\ConfigurableNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification to the requester when their booking is rejected. Database
 * (in-app inbox) + mail. Queued. Dispatched by RejectBookingAction after its
 * transaction commits. Carries the rejection reason.
 *
 * @see RejectBookingAction
 */
final class BookingRejectedNotification extends Notification implements ShouldQueue
{
    use ConfigurableNotification;
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
    ) {}

    public function notificationType(): NotificationType
    {
        return NotificationType::BookingRejected;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tz = (string) config('app.display_timezone', 'Asia/Jakarta');
        $name = $notifiable instanceof User ? $notifiable->name : 'Pengguna';
        $waktu = $this->booking->starts_at->copy()->setTimezone($tz)
            ->locale('id')->isoFormat('dddd, D MMMM Y [pukul] HH:mm');

        $mail = (new MailMessage)
            ->subject('Reservasi Ditolak: '.$this->booking->booking_code)
            ->greeting('Halo '.$name.',')
            ->line(sprintf('Reservasi %s ditolak.', $this->booking->booking_code))
            ->line('Subjek: '.$this->booking->subject)
            ->line('Ruang: '.($this->booking->room->name ?? '-'))
            ->line('Waktu: '.$waktu);

        if ($this->booking->rejection_reason !== null && $this->booking->rejection_reason !== '') {
            $mail->line('Alasan: '.$this->booking->rejection_reason);
        }

        return $mail->action('Lihat Reservasi', route('bookings.show', $this->booking));
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
            'url' => route('bookings.show', $this->booking),
        ];
    }
}
