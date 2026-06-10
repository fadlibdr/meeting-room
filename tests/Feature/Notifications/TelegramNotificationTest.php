<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\Channels\TelegramChannel;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AppSettingsSeeder::class);
    }

    private function booking(): Booking
    {
        $room = Room::factory()->create();

        return Booking::factory()->approved()->create([
            'resource_id' => $room->id,
            'booking_code' => 'BK-TELE-1',
        ]);
    }

    private function enableTelegram(string $token = 'TEST:token'): void
    {
        app(SettingsService::class)->set('telegram.enabled', true);
        config(['services.telegram.bot_token' => $token, 'services.telegram.api_base' => 'https://api.telegram.org']);
    }

    public function test_via_includes_telegram_when_enabled_and_chat_id_present(): void
    {
        $this->enableTelegram();
        $user = User::factory()->create(['telegram_chat_id' => '123456']);

        $channels = (new BookingApprovedNotification($this->booking()))->via($user);

        $this->assertContains(TelegramChannel::class, $channels);
    }

    public function test_via_excludes_telegram_when_disabled(): void
    {
        app(SettingsService::class)->set('telegram.enabled', false);
        $user = User::factory()->create(['telegram_chat_id' => '123456']);

        $channels = (new BookingApprovedNotification($this->booking()))->via($user);

        $this->assertNotContains(TelegramChannel::class, $channels);
    }

    public function test_via_excludes_telegram_when_user_has_no_chat_id(): void
    {
        $this->enableTelegram();
        $user = User::factory()->create(['telegram_chat_id' => null]);

        $channels = (new BookingApprovedNotification($this->booking()))->via($user);

        $this->assertNotContains(TelegramChannel::class, $channels);
    }

    public function test_notification_posts_to_the_telegram_send_message_api(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        $this->enableTelegram('BOTTOKEN');

        $user = User::factory()->create(['telegram_chat_id' => '987654', 'email_notifications' => false]);

        $user->notify(new BookingApprovedNotification($this->booking()));

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/botBOTTOKEN/sendMessage')
                && $request['chat_id'] === '987654'
                && str_contains((string) $request['text'], 'BK-TELE-1');
        });
    }

    public function test_channel_noops_without_a_bot_token(): void
    {
        Http::fake();
        app(SettingsService::class)->set('telegram.enabled', true);
        config(['services.telegram.bot_token' => '']); // no token

        $user = User::factory()->create(['telegram_chat_id' => '987654', 'email_notifications' => false]);

        // Send directly through the channel — must not hit the network.
        (new TelegramChannel)->send($user, new BookingApprovedNotification($this->booking()));

        Http::assertNothingSent();
    }
}
