<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Models\Booking;
use App\Models\BookingCalendarEvent;
use App\Models\CalendarConnection;
use App\Models\Resource;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stage 3 F.2b/c — mirrors a booking into connected external calendars.
 *
 * Delegated mode (implemented + tested): writes to each calendar the booking's
 * requester has connected (calendar_connections), recording the external event
 * id so later updates/deletes target it. Application mode is config-gated and
 * left as an activation stub (needs app-token + per-mailbox iteration).
 */
class CalendarSyncService
{
    public const UPSERT = 'upsert';

    public const DELETE = 'delete';

    public function sync(Booking $booking, string $action): void
    {
        if (! config('calendar.sync.enabled')) {
            return;
        }

        if (config('calendar.sync.consent_mode') === 'application') {
            // Activation stub: application/admin-consent mode writes to each
            // attendee mailbox with an app token. Wired once creds + consent exist.
            Log::info('Calendar sync (application mode) not yet activated', ['booking' => $booking->id]);

            return;
        }

        $connections = CalendarConnection::query()
            ->where('user_id', $booking->requester_user_id)
            ->where('is_active', true)
            ->get();

        foreach ($connections as $connection) {
            $provider = $this->providerFor($connection->provider);
            if ($provider === null) {
                continue;
            }

            try {
                $this->push($provider, $connection, $booking, $action);
            } catch (Throwable $e) {
                Log::warning('Calendar sync failed', [
                    'booking' => $booking->id,
                    'provider' => $connection->provider,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function push(CalendarProvider $provider, CalendarConnection $connection, Booking $booking, string $action): void
    {
        $token = (string) $connection->access_token; // refresh-on-expiry: activation follow-up
        $calendarId = $connection->external_calendar_id;

        /** @var BookingCalendarEvent|null $mapping */
        $mapping = BookingCalendarEvent::query()
            ->where('booking_id', $booking->id)
            ->where('provider', $provider->key())
            ->where('target_user_id', $connection->user_id)
            ->first();

        if ($action === self::DELETE) {
            if ($mapping !== null) {
                $provider->deleteEvent($token, $calendarId, $mapping->external_event_id);
                $mapping->delete();
            }

            return;
        }

        $event = $this->normalize($booking);

        if ($mapping !== null) {
            $provider->updateEvent($token, $calendarId, $mapping->external_event_id, $event);

            return;
        }

        $externalId = $provider->createEvent($token, $calendarId, $event);
        BookingCalendarEvent::create([
            'booking_id' => $booking->id,
            'provider' => $provider->key(),
            'target_user_id' => $connection->user_id,
            'external_event_id' => $externalId,
        ]);
    }

    private function providerFor(string $key): ?CalendarProvider
    {
        return match (true) {
            $key === 'microsoft' && (bool) config('calendar.microsoft.enabled') => new MicrosoftGraphProvider,
            $key === 'google' && (bool) config('calendar.google.enabled') => new GoogleCalendarProvider,
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function normalize(Booking $booking): array
    {
        $resource = $booking->resource;
        $location = $resource instanceof Resource ? $resource->name : '-';

        $description = 'Kode Reservasi: '.$booking->booking_code;
        if ($booking->agenda !== null && $booking->agenda !== '') {
            $description .= "\n".$booking->agenda;
        }

        return [
            'subject' => $booking->subject,
            'description' => $description,
            'location' => $location,
            'starts_at' => $booking->starts_at->copy()->utc()->toIso8601String(),
            'ends_at' => $booking->ends_at->copy()->utc()->toIso8601String(),
        ];
    }
}
