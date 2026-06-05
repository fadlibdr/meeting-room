<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\BlockRoomAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification to the requester of a booking that was cancelled because an
 * admin created a room block over its slot (§H.7 force-cancel). Database
 * (in-app inbox) + mail. Queued. Dispatched by BlockRoomAction after commit.
 *
 * @see BlockRoomAction
 */
final class RoomBlockCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly RoomBlockSchedule $block,
        private readonly Booking $cancelledBooking,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (app(SettingsService::class)->get('notifications.send_email_default', false)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $tz = (string) config('app.display_timezone', 'Asia/Jakarta');
        $name = $notifiable instanceof User ? $notifiable->name : 'Pengguna';
        $waktu = $this->cancelledBooking->starts_at->copy()->setTimezone($tz)
            ->locale('id')->isoFormat('dddd, D MMMM Y [pukul] HH:mm');

        return (new MailMessage)
            ->subject('Reservasi Dibatalkan — Ruang Diblokir: '.$this->cancelledBooking->booking_code)
            ->greeting('Halo '.$name.',')
            ->line(sprintf(
                'Reservasi %s dibatalkan karena ruang diblokir (%s).',
                $this->cancelledBooking->booking_code,
                $this->block->block_type->label(),
            ))
            ->line('Ruang: '.($this->block->room->name ?? '-'))
            ->line('Waktu: '.$waktu)
            ->action('Lihat Reservasi', route('bookings.show', $this->cancelledBooking->id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::RoomBlockCreated->value,
            'block_id' => $this->block->id,
            'booking_id' => $this->cancelledBooking->id,
            'booking_code' => $this->cancelledBooking->booking_code,
            'room_id' => $this->block->room_id,
            'block_type' => $this->block->block_type->value,
            'message' => sprintf(
                'Reservasi %s dibatalkan karena ruang diblokir (%s).',
                $this->cancelledBooking->booking_code,
                $this->block->block_type->label(),
            ),
            'url' => route('bookings.show', $this->cancelledBooking->id),
        ];
    }
}
