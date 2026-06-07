<?php

declare(strict_types=1);

namespace Tests\Feature\Calendar;

use App\Jobs\SyncBookingToCalendarsJob;
use App\Models\Booking;
use App\Models\BookingCalendarEvent;
use App\Models\CalendarConnection;
use App\Models\User;
use App\Services\Calendar\CalendarSyncDispatcher;
use App\Services\Calendar\CalendarSyncService;
use App\Services\Calendar\GoogleCalendarProvider;
use App\Services\Calendar\MicrosoftGraphProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CalendarTwoWaySyncTest extends TestCase
{
    use RefreshDatabase;

    private function enable(): void
    {
        config([
            'calendar.sync.enabled' => true,
            'calendar.sync.consent_mode' => 'delegated',
            'calendar.microsoft.enabled' => true,
            'calendar.microsoft.graph_base' => 'https://graph.microsoft.com/v1.0',
            'calendar.google.enabled' => true,
            'calendar.google.api_base' => 'https://www.googleapis.com/calendar/v3',
        ]);
    }

    private function event(): array
    {
        return [
            'subject' => 'Rapat', 'description' => 'Kode: X', 'location' => 'Ruang A',
            'starts_at' => '2026-07-01T09:00:00+00:00', 'ends_at' => '2026-07-01T10:00:00+00:00',
        ];
    }

    public function test_microsoft_driver_creates_updates_and_deletes_events(): void
    {
        Http::fake([
            'graph.microsoft.com/*' => Http::response(['id' => 'evt-ms-1'], 200),
        ]);
        config(['calendar.microsoft.graph_base' => 'https://graph.microsoft.com/v1.0']);

        $driver = new MicrosoftGraphProvider;
        $id = $driver->createEvent('tok', null, $this->event());
        $this->assertSame('evt-ms-1', $id);

        $driver->updateEvent('tok', null, 'evt-ms-1', $this->event());
        $driver->deleteEvent('tok', null, 'evt-ms-1');

        Http::assertSent(fn ($r) => $r->method() === 'POST' && str_contains($r->url(), '/me/events'));
        Http::assertSent(fn ($r) => $r->method() === 'PATCH' && str_contains($r->url(), '/me/events/evt-ms-1'));
        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_contains($r->url(), '/me/events/evt-ms-1'));
    }

    public function test_google_driver_targets_the_calendar_events_collection(): void
    {
        Http::fake(['www.googleapis.com/*' => Http::response(['id' => 'evt-g-1'], 200)]);

        $id = (new GoogleCalendarProvider)->createEvent('tok', null, $this->event());

        $this->assertSame('evt-g-1', $id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/calendars/primary/events') && $r['summary'] === 'Rapat');
    }

    public function test_sync_creates_then_updates_then_deletes_a_mapping(): void
    {
        $this->enable();
        Http::fake(['graph.microsoft.com/*' => Http::response(['id' => 'evt-ms-9'], 200)]);

        $user = User::factory()->create();
        CalendarConnection::factory()->create(['user_id' => $user->id, 'provider' => 'microsoft']);
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

        $service = app(CalendarSyncService::class);

        // Create
        $service->sync($booking, CalendarSyncService::UPSERT);
        $this->assertDatabaseHas('booking_calendar_events', [
            'booking_id' => $booking->id, 'provider' => 'microsoft', 'external_event_id' => 'evt-ms-9',
        ]);

        // Update (mapping exists -> PATCH, no new row)
        $service->sync($booking, CalendarSyncService::UPSERT);
        $this->assertSame(1, BookingCalendarEvent::where('booking_id', $booking->id)->count());

        // Delete (mapping removed)
        $service->sync($booking, CalendarSyncService::DELETE);
        $this->assertDatabaseMissing('booking_calendar_events', ['booking_id' => $booking->id]);

        Http::assertSent(fn ($r) => $r->method() === 'PATCH');
        Http::assertSent(fn ($r) => $r->method() === 'DELETE');
    }

    public function test_sync_is_a_noop_when_disabled(): void
    {
        config(['calendar.sync.enabled' => false]);
        Http::fake();

        $user = User::factory()->create();
        CalendarConnection::factory()->create(['user_id' => $user->id]);
        $booking = Booking::factory()->approved()->create(['requester_user_id' => $user->id]);

        app(CalendarSyncService::class)->sync($booking, CalendarSyncService::UPSERT);

        Http::assertNothingSent();
        $this->assertSame(0, BookingCalendarEvent::count());
    }

    public function test_dispatcher_queues_the_job_only_when_enabled(): void
    {
        Queue::fake();
        $booking = Booking::factory()->approved()->create();
        $dispatcher = app(CalendarSyncDispatcher::class);

        config(['calendar.sync.enabled' => false]);
        $dispatcher->dispatch($booking, CalendarSyncService::UPSERT);
        Queue::assertNothingPushed();

        config(['calendar.sync.enabled' => true]);
        $dispatcher->dispatch($booking, CalendarSyncService::UPSERT);
        Queue::assertPushed(SyncBookingToCalendarsJob::class);
    }
}
