<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper over the Telegram Bot API — used by the notification channel,
 * the /start webhook, and the telegram:* console commands.
 *
 * The bot token comes from config('services.telegram.bot_token'), which the
 * RuntimeSettingsServiceProvider layers from the encrypted DB setting at boot.
 */
class TelegramBot
{
    public function isConfigured(): bool
    {
        return $this->token() !== '';
    }

    public function token(): string
    {
        return (string) config('services.telegram.bot_token', '');
    }

    public function sendMessage(int|string $chatId, string $text): Response
    {
        return Http::asJson()->post($this->endpoint('sendMessage'), [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'disable_web_page_preview' => true,
        ]);
    }

    public function setWebhook(string $url): Response
    {
        return Http::asJson()->post($this->endpoint('setWebhook'), ['url' => $url]);
    }

    public function deleteWebhook(): Response
    {
        return Http::asJson()->post($this->endpoint('deleteWebhook'), []);
    }

    private function endpoint(string $method): string
    {
        $base = rtrim((string) config('services.telegram.api_base', 'https://api.telegram.org'), '/');

        return "{$base}/bot{$this->token()}/{$method}";
    }
}
