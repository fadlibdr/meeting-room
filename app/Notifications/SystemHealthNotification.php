<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Ops alert sent to administrators when `system:health-check` finds problems.
 *
 * Deliberately NOT ShouldQueue and sent with Notification::sendNow — a primary
 * failure mode is the queue worker being down, so the alert must never depend
 * on the queue. Always uses both channels (in-app + mail) regardless of the
 * notifications.send_email_default toggle: an ops alert must get through.
 */
final class SystemHealthNotification extends Notification
{
    /**
     * @param  array<int, string>  $issues
     */
    public function __construct(private readonly array $issues) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'system.health',
            'message' => sprintf('Pemeriksaan kesehatan sistem menemukan %d masalah.', count($this->issues)),
            'issues' => $this->issues,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->error()
            ->subject('[Peringatan] Kesehatan Sistem — Meeting Room BPJS Kesehatan')
            ->greeting('Halo,')
            ->line('Pemeriksaan kesehatan sistem menemukan masalah berikut:');

        foreach ($this->issues as $issue) {
            $mail->line('• '.$issue);
        }

        return $mail->line('Mohon segera diperiksa.');
    }
}
