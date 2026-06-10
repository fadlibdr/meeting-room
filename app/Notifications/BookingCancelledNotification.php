<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\CancelBookingAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsToTelegram;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification to a booking's assigned approver when the booking is cancelled.
 * Database (in-app inbox) + mail. Queued. Dispatched by CancelBookingAction
 * after commit — NOT for Draft cancellations (never visible to an approver) and
 * NOT when the reschedule pipeline suppresses it (M3-Dec-3).
 *
 * @see CancelBookingAction
 */
final class BookingCancelledNotification extends Notification implements ShouldQueue
{
    use BroadcastsToTelegram;
    use Queueable;

    public function __construct(
        private readonly Booking $booking,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        $globalOn = (bool) app(SettingsService::class)->get('notifications.send_email_default', false);
        $userOptIn = ! $notifiable instanceof User || $notifiable->email_notifications;

        if ($globalOn && $userOptIn) {
            $channels[] = 'mail';
        }

        return array_merge($channels, $this->telegramChannels($notifiable));
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tz = (string) config('app.display_timezone', 'Asia/Jakarta');
        $name = $notifiable instanceof User ? $notifiable->name : 'Pengguna';
        $waktu = $this->booking->starts_at->copy()->setTimezone($tz)
            ->locale('id')->isoFormat('dddd, D MMMM Y [pukul] HH:mm');

        $mail = (new MailMessage)
            ->subject('Reservasi Dibatalkan: '.$this->booking->booking_code)
            ->greeting('Halo '.$name.',')
            ->line(sprintf('Reservasi %s dibatalkan.', $this->booking->booking_code))
            ->line('Subjek: '.$this->booking->subject)
            ->line('Ruang: '.($this->booking->room->name ?? '-'))
            ->line('Waktu: '.$waktu);

        if ($this->booking->cancellation_reason !== null && $this->booking->cancellation_reason !== '') {
            $mail->line('Alasan: '.$this->booking->cancellation_reason);
        }

        return $mail->action('Tinjau Reservasi', route('bookings.show', $this->booking->id));
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
