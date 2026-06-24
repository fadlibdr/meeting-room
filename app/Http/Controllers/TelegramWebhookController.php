<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TelegramBot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Telegram bot webhook (auto-capture chat ids on /start).
 *
 * Telegram POSTs updates to /telegram/webhook/{secret}. The secret path segment
 * (config('services.telegram.webhook_secret')) is the only guard — the route is
 * public + CSRF-exempt. Always replies 200 so Telegram does not retry.
 *
 * /start <token>  → links the chat to the user who owns that one-time link token
 *                   (generated from the profile "Hubungkan Telegram" button).
 * /start          → replies with the chat id so the user can paste it manually.
 */
class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, TelegramBot $bot): JsonResponse
    {
        $configured = (string) config('services.telegram.webhook_secret', '');
        abort_if($configured === '' || ! hash_equals($configured, $secret), 404);

        $update = $request->all();
        $chatId = data_get($update, 'message.chat.id');
        $text = trim((string) data_get($update, 'message.text', ''));

        if ($chatId !== null && str_starts_with($text, '/start')) {
            $this->handleStart($bot, (string) $chatId, trim((string) substr($text, strlen('/start'))));
        }

        return response()->json(['ok' => true]);
    }

    private function handleStart(TelegramBot $bot, string $chatId, string $param): void
    {
        if ($param !== '') {
            $user = User::where('telegram_link_token_hash', User::hashToken($param))
                ->first();

            if ($user instanceof User) {
                $user->forceFill([
                    'telegram_chat_id' => $chatId,
                    'telegram_link_token' => null,
                    'telegram_link_token_hash' => null,
                ])->save();

                $bot->sendMessage($chatId, sprintf(
                    "✅ <b>Terhubung.</b>\nAkun <b>%s</b> kini akan menerima notifikasi reservasi di sini.",
                    e($user->name),
                ));

                return;
            }

            $bot->sendMessage($chatId, '⚠️ Tautan tidak valid atau sudah dipakai. Buka kembali halaman profil dan tekan "Hubungkan Telegram".');

            return;
        }

        // Plain /start — let the user copy their chat id for manual entry.
        $bot->sendMessage($chatId, sprintf(
            "Halo! Chat ID Anda adalah:\n<code>%s</code>\n\nTempel di profil Anda (Telegram Chat ID), atau gunakan tombol \"Hubungkan Telegram\" di profil untuk menautkan otomatis.",
            $chatId,
        ));
    }
}
