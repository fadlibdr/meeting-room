<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Models\Booking;
use App\Models\CalendarConnection;
use App\Models\User;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Tests\TestCase;

class CalendarConnectTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'calendar.sync.enabled' => true,
            'calendar.microsoft.enabled' => true,
            'calendar.microsoft.tenant' => 'tenant-1',
            'calendar.microsoft.client_id' => 'cid',
            'calendar.microsoft.client_secret' => 'csecret',
            'calendar.microsoft.graph_base' => 'https://graph.microsoft.com/v1.0',
            'calendar.google.enabled' => false,
        ]);
    }

    public function test_connect_404s_when_sync_or_provider_disabled(): void
    {
        $user = User::factory()->create();
        config(['calendar.sync.enabled' => false]);
        $this->actingAs($user)->get(route('calendar.connect', ['provider' => 'microsoft']))->assertNotFound();

        config(['calendar.sync.enabled' => true, 'calendar.microsoft.enabled' => false]);
        $this->actingAs($user)->get(route('calendar.connect', ['provider' => 'microsoft']))->assertNotFound();

        $this->enable();
        $this->actingAs($user)->get(route('calendar.connect', ['provider' => 'nope']))->assertNotFound();
    }

    public function test_callback_stores_an_encrypted_connection(): void
    {
        $this->enable();
        $user = User::factory()->create();

        $identity = new SocialiteUser;
        $identity->token = 'access-xyz';
        $identity->refreshToken = 'refresh-xyz';
        $identity->expiresIn = 3600;

        $driver = Mockery::mock();
        $driver->shouldReceive('redirectUrl')->andReturnSelf();
        $driver->shouldReceive('user')->andReturn($identity);
        Socialite::shouldReceive('driver')->with('azure')->andReturn($driver);

        $this->actingAs($user)
            ->get(route('calendar.connect.callback', ['provider' => 'microsoft']))
            ->assertRedirect(route('calendar-subscription.index'));

        $conn = CalendarConnection::where('user_id', $user->id)->where('provider', 'microsoft')->firstOrFail();
        $this->assertSame('access-xyz', $conn->access_token);   // decrypted via cast
        $this->assertSame('refresh-xyz', $conn->refresh_token);
        $this->assertNotNull($conn->token_expires_at);
        // Stored ciphertext is not the plaintext.
        $this->assertNotSame('access-xyz', $conn->getRawOriginal('access_token'));
    }

    public function test_disconnect_removes_the_connection(): void
    {
        $this->enable();
        $user = User::factory()->create();
        CalendarConnection::factory()->create(['user_id' => $user->id, 'provider' => 'microsoft']);

        $this->actingAs($user)
            ->delete(route('calendar.disconnect', ['provider' => 'microsoft']))
            ->assertRedirect(route('calendar-subscription.index'));

        $this->assertDatabaseMissing('calendar_connections', ['user_id' => $user->id, 'provider' => 'microsoft']);
    }

    public function test_expired_token_is_refreshed_before_pushing(): void
    {
        $this->enable();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['access_token' => 'fresh-token', 'expires_in' => 3600], 200),
            'graph.microsoft.com/*' => Http::response(['id' => 'evt-1'], 200),
        ]);

        $user = User::factory()->create();
        $conn = CalendarConnection::factory()->create([
            'user_id' => $user->id, 'provider' => 'microsoft',
            'access_token' => 'stale', 'refresh_token' => 'r1',
            'token_expires_at' => now()->subHour(),
        ]);
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

        app(CalendarSyncService::class)->sync($booking, CalendarSyncService::UPSERT);

        Http::assertSent(fn ($r) => str_contains($r->url(), 'login.microsoftonline.com') && $r['grant_type'] === 'refresh_token');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.microsoft.com') && $r->hasHeader('Authorization', 'Bearer fresh-token'));
        $this->assertSame('fresh-token', $conn->fresh()->access_token);
    }
}
