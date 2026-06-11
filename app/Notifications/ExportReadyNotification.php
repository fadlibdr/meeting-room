<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Jobs\GenerateBookingExportJob;
use App\Models\Export;
use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user their queued export is ready to download. Database (in-app inbox)
 * + mail (gated by the global toggle AND the user's per-user opt-in, like the
 * booking notifications). Dispatched by GenerateBookingExportJob on success.
 *
 * @see GenerateBookingExportJob
 */
final class ExportReadyNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly Export $export,
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

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable instanceof User ? $notifiable->name : 'Pengguna';

        return (new MailMessage)
            ->subject('Ekspor Data Selesai')
            ->greeting('Halo '.$name.',')
            ->line(sprintf(
                'Ekspor data reservasi Anda (%d baris) telah selesai diproses.',
                $this->export->row_count ?? 0,
            ))
            ->line('Tautan unduhan berlaku hingga '.($this->export->expires_at?->setTimezone(
                (string) config('app.display_timezone', 'Asia/Jakarta')
            )->locale('id')->isoFormat('D MMMM Y, HH:mm') ?? '-').'.')
            ->action('Unduh Berkas', route('exports.download', $this->export));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::ExportReady->value,
            'export_id' => $this->export->id,
            'format' => $this->export->format->value,
            'row_count' => $this->export->row_count,
            'subject' => 'Ekspor data selesai',
            'message' => sprintf(
                'Ekspor data reservasi (%d baris) siap diunduh.',
                $this->export->row_count ?? 0,
            ),
            'url' => route('exports.download', $this->export),
        ];
    }
}
