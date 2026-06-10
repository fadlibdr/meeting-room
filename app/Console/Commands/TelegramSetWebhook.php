<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TelegramBot;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\URL;

/**
 * Registers (or removes) the Telegram bot webhook so /start updates reach
 * /telegram/webhook/{secret}. Run once after setting the token + webhook secret.
 *
 *   php artisan telegram:set-webhook
 *   php artisan telegram:set-webhook --delete
 */
class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {--delete : Remove the webhook instead of setting it}';

    protected $description = 'Register the Telegram bot webhook URL (auto chat-id capture).';

    public function handle(TelegramBot $bot): int
    {
        if (! $bot->isConfigured()) {
            $this->error('Bot token is not configured.');

            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $response = $bot->deleteWebhook();
            $this->line($response->body());

            return $response->successful() ? self::SUCCESS : self::FAILURE;
        }

        $secret = (string) config('services.telegram.webhook_secret', '');
        if ($secret === '') {
            $this->error('services.telegram.webhook_secret is empty — set it in Settings → Telegram first.');

            return self::FAILURE;
        }

        $url = URL::route('telegram.webhook', ['secret' => $secret]);
        $response = $bot->setWebhook($url);

        if ($response->successful() && (bool) $response->json('ok')) {
            $this->info('Webhook set to: '.$url);

            return self::SUCCESS;
        }

        $this->error("Telegram API error (HTTP {$response->status()}): ".$response->body());

        return self::FAILURE;
    }
}
