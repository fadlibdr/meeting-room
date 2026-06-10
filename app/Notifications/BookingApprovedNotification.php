<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\ApproveBookingAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\Concerns\BroadcastsToTelegram;
use App\Services\IcsGenerator;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification to the requester when their booking is approved. Database
 * (in-app inbox) + mail. Queued. Dispatched by ApproveBookingAction after its
 * transaction commits. Auto-approval at submit does NOT fire this (2D-F-Dec-5).
 *
 * @see ApproveBookingAction
 */
final class BookingApprovedNotification extends Notification implements ShouldQueue
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
        $ics = app(IcsGenerator::class);

        return (new MailMessage)
            ->subject('Reservasi Disetujui: '.$this->booking->booking_code)
            ->greeting('Halo '.$name.',')
            ->line(sprintf('Reservasi %s telah disetujui.', $this->booking->booking_code))
            ->line('Subjek: '.$this->booking->subject)
            ->line('Ruang: '.($this->booking->room->name ?? '-'))
            ->line('Waktu: '.$waktu)
            ->action('Lihat Reservasi', route('bookings.show', $this->booking->id))
            ->attachData(
                $ics->forBooking($this->booking),
                $ics->filename($this->booking),
                ['mime' => 'text/calendar; charset=utf-8; method=PUBLISH'],
            );
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
