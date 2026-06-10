<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;
use App\Notifications\Channels\TelegramChannel;
use App\Services\SettingsService;

/**
 * Adds the Telegram channel to a notification when:
 *  - the notifiable is a User with a telegram_chat_id, and
 *  - the global `telegram.enabled` setting is on.
 *
 * The notification's toArray() supplies the message; toTelegram() renders it
 * as a small HTML message. via() should merge in telegramChannels($notifiable).
 */
trait BroadcastsToTelegram
{
    /**
     * @return array<int, class-string<TelegramChannel>>
     */
    protected function telegramChannels(object $notifiable): array
    {
        if (! $notifiable instanceof User || blank($notifiable->telegram_chat_id)) {
            return [];
        }

        if (! (bool) app(SettingsService::class)->get('telegram.enabled', false)) {
            return [];
        }

        return [TelegramChannel::class];
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function toArray(object $notifiable): array;

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
