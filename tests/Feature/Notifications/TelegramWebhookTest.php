<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.telegram.webhook_secret' => 'sekret',
            'services.telegram.bot_token' => 'BOTTOKEN',
        ]);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    private function update(string $text, int $chatId = 555): array
    {
        return ['message' => ['chat' => ['id' => $chatId], 'text' => $text]];
    }

    public function test_wrong_secret_404s(): void
    {
        $this->postJson(route('telegram.webhook', ['secret' => 'WRONG']), $this->update('/start'))
            ->assertNotFound();
    }

    public function test_webhook_disabled_when_secret_unset(): void
    {
        config(['services.telegram.webhook_secret' => '']);

        $this->postJson('/telegram/webhook/anything', $this->update('/start'))
            ->assertNotFound();
    }

    public function test_start_with_token_links_the_user_and_clears_token(): void
    {
        $user = User::factory()->create(['telegram_chat_id' => null]);
        $user->forceFill(['telegram_link_token' => 'tok123', 'telegram_link_token_hash' => User::hashToken('tok123')])->save();

        $this->postJson(route('telegram.webhook', ['secret' => 'sekret']), $this->update('/start tok123', 6129))
            ->assertOk()
            ->assertJson(['ok' => true]);

        $user->refresh();
        $this->assertSame('6129', $user->telegram_chat_id);
        $this->assertNull($user->telegram_link_token);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/sendMessage')
            && (string) $r['chat_id'] === '6129'
            && str_contains((string) $r['text'], 'Terhubung'));
    }

    public function test_plain_start_replies_with_chat_id(): void
    {
        $this->postJson(route('telegram.webhook', ['secret' => 'sekret']), $this->update('/start', 4242))
            ->assertOk();

        Http::assertSent(fn ($r) => str_contains((string) $r['text'], '4242'));
    }

    public function test_unknown_token_does_not_link_anyone(): void
    {
        $user = User::factory()->create(['telegram_link_token' => 'realtok', 'telegram_chat_id' => null]);

        $this->postJson(route('telegram.webhook', ['secret' => 'sekret']), $this->update('/start bogus', 9))
            ->assertOk();

        $this->assertNull($user->fresh()->telegram_chat_id);
    }

    public function test_non_start_messages_are_ignored(): void
    {
        $this->postJson(route('telegram.webhook', ['secret' => 'sekret']), $this->update('hello there'))
            ->assertOk();

        Http::assertNothingSent();
    }
}
