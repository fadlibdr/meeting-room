<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Sent to the NEW email address when a user changes their email. Clicking the
 * link confirms ownership and applies the change.
 */
final class EmailChangeVerificationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly string $name,
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
        $url = URL::signedRoute('email.change.verify', ['token' => $this->token]);

        return (new MailMessage)
            ->subject(__('Konfirmasi Perubahan Email'))
            ->greeting(__('Halo :name,', ['name' => $this->name]))
            ->line(__('Kami menerima permintaan untuk mengubah alamat email akun Anda menjadi alamat ini.'))
            ->action(__('Konfirmasi Email Baru'), $url)
            ->line(__('Jika Anda tidak meminta perubahan ini, abaikan email ini — email akun Anda tidak akan berubah.'));
    }
}
