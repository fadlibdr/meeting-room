<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Stage 3 D — the scheduled utilization/booking report, emailed to admins.
 *
 * Mail-only (not an in-app inbox item) and queued. Unlike the booking
 * notifications it is NOT gated by the per-user opt-in: it is an explicit
 * admin-scheduled feature whose audience is resolved by permission. The XLSX is
 * attached from the report disk (the worker reads it at send time).
 */
final class ScheduledReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $summary  RoomUtilizationReport summary block
     */
    public function __construct(
        private readonly string $periodLabel,
        private readonly string $fromLabel,
        private readonly string $toLabel,
        private readonly array $summary,
        private readonly string $attachmentPath,
        private readonly string $attachmentName,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $disk = (string) config('reports.report_disk', 'local_private');

        return (new MailMessage)
            ->subject(sprintf('Laporan Reservasi %s: %s – %s', $this->periodLabel, $this->fromLabel, $this->toLabel))
            ->greeting('Laporan Reservasi '.$this->periodLabel)
            ->line(sprintf('Periode: %s s.d. %s.', $this->fromLabel, $this->toLabel))
            ->line(sprintf('Utilisasi rata-rata: %s%%.', $this->summary['utilization'] ?? 0))
            ->line(sprintf('Reservasi aktif: %s dari %s total.', $this->summary['active_bookings'] ?? 0, $this->summary['total_bookings'] ?? 0))
            ->line(sprintf('Tingkat pembatalan: %s%% · No-show: %s%%.', $this->summary['cancellation_rate'] ?? 0, $this->summary['no_show_rate'] ?? 0))
            ->line('Rincian lengkap reservasi terlampir dalam berkas Excel.')
            ->attach(Storage::disk($disk)->path($this->attachmentPath), [
                'as' => $this->attachmentName,
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);
    }
}
