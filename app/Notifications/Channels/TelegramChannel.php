<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Custom Telegram notification channel — sends a message via the Telegram Bot
 * API (no third-party package). The notification must implement toTelegram().
 *
 * Resolution:
 *  - chat id: $notifiable->routeNotificationFor('telegram') ?? ->telegram_chat_id
 *  - bot token: config('services.telegram.bot_token') (DB setting overrides .env)
 *
 * No-ops (without throwing) when the token or chat id is missing, so a
 * misconfiguration never breaks the queued notification pipeline.
 */
class TelegramChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toTelegram')) {
            return;
        }

        $chatId = $notifiable->routeNotificationFor('telegram', $notification)
            ?? ($notifiable->telegram_chat_id ?? null);

        $token = (string) config('services.telegram.bot_token', '');

        if (blank($chatId) || $token === '') {
            return;
        }

        $message = (string) $notification->toTelegram($notifiable);
        if ($message === '') {
            return;
        }

        $base = rtrim((string) config('services.telegram.api_base', 'https://api.telegram.org'), '/');

        $response = Http::asJson()
            ->post("{$base}/bot{$token}/sendMessage", [
                'chat_id' => $chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if ($response->failed()) {
            Log::warning('telegram.notification.failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);
        }
    }
}
