<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Enums\NotificationType;
use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Services\NotificationPreferenceResolver;
use App\Services\SettingsService;

/**
 * Drives a notification's channels from the configurable matrix
 * (NotificationPreferenceResolver): admin default per (type, channel) + the
 * user's override. Infra availability is still enforced here — email respects
 * the user's master email toggle; Telegram needs a chat id + the global flag.
 *
 * Using notifications must declare notificationType() and provide toArray().
 */
trait ConfigurableNotification
{
    abstract public function notificationType(): NotificationType;

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $resolved = app(NotificationPreferenceResolver::class)
            ->channelsFor($notifiable, $this->notificationType());

        $channels = [];

        if (in_array('database', $resolved, true)) {
            $channels[] = 'database';
        }

        if (in_array('mail', $resolved, true) && $notifiable->email_notifications) {
            $channels[] = 'mail';
        }

        if (in_array('telegram', $resolved, true)
            && filled($notifiable->telegram_chat_id)
            && (bool) app(SettingsService::class)->get('telegram.enabled', false)) {
            $channels[] = TelegramChannel::class;
        }

        return $channels;
    }

    public function toTelegram(object $notifiable): string
    {
        $data = $this->toArray($notifiable);

        $lines = [];
        if (! empty($data['subject'])) {
            $lines[] = '<b>'.e((string) $data['subject']).'</b>';
        }
        if (! empty($data['message'])) {
            $lines[] = e((string) $data['message']);
        }
        if (! empty($data['url'])) {
            $lines[] = (string) $data['url'];
        }

        return implode("\n", $lines);
    }
}
