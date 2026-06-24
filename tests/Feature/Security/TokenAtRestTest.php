<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Release D — bearer tokens encrypted at rest with hash-for-lookup
 * (SOC 2 CC6.1 / ISO 27001 A.8.24) + configurable calendar-feed policy.
 */
class TokenAtRestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AppSettingsSeeder::class);
    }

    public function test_calendar_feed_token_is_encrypted_at_rest_with_a_lookup_hash(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->ensureCalendarFeedToken();

        $stored = DB::table('users')->where('id', $user->id)->first();

        // The column does not hold the plaintext token.
        $this->assertNotSame($token, $stored->calendar_feed_token);
        $this->assertNotNull($stored->calendar_feed_token);
        // The lookup hash is the SHA-256 of the plaintext.
        $this->assertSame(hash('sha256', $token), $stored->calendar_feed_token_hash);
        // The cast transparently decrypts on read.
        $this->assertSame($token, $user->fresh()->calendar_feed_token);
    }

    public function test_feed_resolves_by_hash_and_rejects_unknown_tokens(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->ensureCalendarFeedToken();

        $this->get(route('calendar.feed', ['token' => $token]))->assertOk();
        $this->get(route('calendar.feed', ['token' => 'definitely-not-a-real-token']))->assertNotFound();
    }

    public function test_feed_can_be_disabled_by_policy(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->ensureCalendarFeedToken();

        app(SettingsService::class)->set('security.calendar_feed_enabled', '0');

        $this->get(route('calendar.feed', ['token' => $token]))->assertNotFound();
    }

    public function test_telegram_link_token_is_encrypted_with_a_lookup_hash(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $token = $user->ensureTelegramLinkToken();

        $stored = DB::table('users')->where('id', $user->id)->first();
        $this->assertNotSame($token, $stored->telegram_link_token);
        $this->assertSame(hash('sha256', $token), $stored->telegram_link_token_hash);

        // Resolvable by the hash (how the /start webhook finds the user).
        $found = User::where('telegram_link_token_hash', User::hashToken($token))->first();
        $this->assertTrue($found->is($user));
    }
}
