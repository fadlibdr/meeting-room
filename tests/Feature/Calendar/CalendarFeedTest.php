<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Livewire\CalendarSubscription;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CalendarFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_invalid_token_returns_404(): void
    {
        $this->get(route('calendar.feed', ['token' => 'nope']))->assertNotFound();
    }

    public function test_feed_returns_the_users_active_bookings_as_ics(): void
    {
        $user = User::factory()->create();
        $token = $user->ensureCalendarFeedToken();

        $mine = Booking::factory()->approved()->create([
            'requester_user_id' => $user->id,
            'subject' => 'Rapat Saya',
            'starts_at' => now()->addDay()->setTime(9, 0),
            'ends_at' => now()->addDay()->setTime(10, 0),
        ]);
        // Another user's booking must not leak into my feed.
        Booking::factory()->approved()->create(['subject' => 'Rapat Orang Lain']);

        $response = $this->get(route('calendar.feed', ['token' => $token]));

        $response->assertOk();
        $this->assertStringContainsString('text/calendar', $response->headers->get('Content-Type'));
        $body = $response->getContent();
        $this->assertStringContainsString('BEGIN:VCALENDAR', $body);
        $this->assertStringContainsString('SUMMARY:Rapat Saya', $body);
        $this->assertStringNotContainsString('Rapat Orang Lain', $body);
        $this->assertStringContainsString('booking-'.$mine->id, $body);
    }

    public function test_feed_excludes_draft_and_cancelled_bookings(): void
    {
        $user = User::factory()->create();
        $token = $user->ensureCalendarFeedToken();

        Booking::factory()->create(['requester_user_id' => $user->id, 'status' => 'draft', 'subject' => 'Draf Rahasia']);
        Booking::factory()->create(['requester_user_id' => $user->id, 'status' => 'cancelled', 'subject' => 'Batal Rahasia']);

        $body = $this->get(route('calendar.feed', ['token' => $token]))->assertOk()->getContent();

        $this->assertStringNotContainsString('Draf Rahasia', $body);
        $this->assertStringNotContainsString('Batal Rahasia', $body);
    }

    public function test_subscription_page_requires_auth(): void
    {
        $this->get(route('calendar-subscription.index'))->assertRedirect(route('login'));
    }

    public function test_subscription_page_shows_feed_url_and_can_rotate_token(): void
    {
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)->test(CalendarSubscription::class);
        $original = $user->fresh()->calendar_feed_token;

        $this->assertNotNull($original);
        $component->assertSee($original);

        $component->call('regenerate')->assertSet('rotated', true);

        $rotated = $user->fresh()->calendar_feed_token;
        $this->assertNotNull($rotated);
        $this->assertNotSame($original, $rotated);

        // Old token URL no longer resolves.
        $this->get(route('calendar.feed', ['token' => $original]))->assertNotFound();
        $this->get(route('calendar.feed', ['token' => $rotated]))->assertOk();
    }
}
