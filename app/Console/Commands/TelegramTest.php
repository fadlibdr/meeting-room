<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\TelegramBot;
use Illuminate\Console\Command;

/**
 * Sends a test Telegram message — a quick way to verify the bot token + a chat
 * id work, independent of the queue/notification pipeline.
 *
 *   php artisan telegram:test               # → first user with a chat id
 *   php artisan telegram:test 123456789     # → an explicit chat id
 */
class TelegramTest extends Command
{
    protected $signature = 'telegram:test {chat? : Chat id (defaults to the first user with one)} {--message= : Custom message}';

    protected $description = 'Send a test message via the Telegram bot to verify configuration.';

    public function handle(TelegramBot $bot): int
    {
        if (! $bot->isConfigured()) {
            $this->error('Bot token is not configured (services.telegram.bot_token / Settings → Telegram).');

            return self::FAILURE;
        }

        $chatId = $this->argument('chat')
            ?? User::query()->whereNotNull('telegram_chat_id')->value('telegram_chat_id');

        if (blank($chatId)) {
            $this->error('No chat id given and no user has a telegram_chat_id set.');

            return self::FAILURE;
        }

        $message = (string) ($this->option('message')
            ?: 'Tes notifikasi Telegram dari Sistem Pemesanan Ruang Rapat.');

        $response = $bot->sendMessage($chatId, $message);

        if ($response->successful() && (bool) $response->json('ok')) {
            $this->info("Sent to chat {$chatId}.");

            return self::SUCCESS;
        }

        $this->error("Telegram API error (HTTP {$response->status()}): ".$response->body());

        return self::FAILURE;
    }
}
