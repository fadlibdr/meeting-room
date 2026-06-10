<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramCommandsTest extends TestCase
{
    use RefreshDatabase;

    public function test_telegram_test_fails_without_a_token(): void
    {
        config(['services.telegram.bot_token' => '']);

        $this->artisan('telegram:test', ['chat' => '123'])->assertExitCode(1);
    }

    public function test_telegram_test_sends_to_the_given_chat(): void
    {
        config(['services.telegram.bot_token' => 'BOTTOKEN']);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $this->artisan('telegram:test', ['chat' => '999'])->assertExitCode(0);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/botBOTTOKEN/sendMessage')
            && (string) $r['chat_id'] === '999');
    }

    public function test_telegram_test_defaults_to_first_user_with_chat_id(): void
    {
        config(['services.telegram.bot_token' => 'BOTTOKEN']);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        User::factory()->create(['telegram_chat_id' => '7777']);

        $this->artisan('telegram:test')->assertExitCode(0);

        Http::assertSent(fn ($r) => (string) $r['chat_id'] === '7777');
    }

    public function test_set_webhook_requires_a_secret(): void
    {
        config(['services.telegram.bot_token' => 'BOTTOKEN', 'services.telegram.webhook_secret' => '']);

        $this->artisan('telegram:set-webhook')->assertExitCode(1);
    }

    public function test_set_webhook_registers_the_url(): void
    {
        config([
            'services.telegram.bot_token' => 'BOTTOKEN',
            'services.telegram.webhook_secret' => 'sekret',
        ]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        $this->artisan('telegram:set-webhook')->assertExitCode(0);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/botBOTTOKEN/setWebhook')
            && str_contains((string) $r['url'], '/telegram/webhook/sekret'));
    }
}
