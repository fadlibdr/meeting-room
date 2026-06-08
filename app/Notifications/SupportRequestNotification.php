<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\SupportRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Stage 4g.1 — notifies the support inbox of a new in-app request. Queued,
 * mail-only. Sent to an on-demand mail route (the configured support address).
 */
final class SupportRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly SupportRequest $request,
        private readonly string $requesterName,
        private readonly string $requesterEmail,
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
        $category = $this->request->category->label();

        $message = (new MailMessage)
            ->subject(sprintf('[Bantuan #%d] %s', $this->request->id, $this->request->subject ?: $category))
            ->greeting('Permintaan Bantuan Baru')
            ->line(sprintf('Kategori: %s', $category))
            ->line(sprintf('Dari: %s (%s)', $this->requesterName, $this->requesterEmail));

        if ($this->request->subject) {
            $message->line(sprintf('Subjek: %s', $this->request->subject));
        }

        return $message
            ->line('Pesan:')
            ->line($this->request->message)
            ->replyTo($this->requesterEmail, $this->requesterName);
    }
}
