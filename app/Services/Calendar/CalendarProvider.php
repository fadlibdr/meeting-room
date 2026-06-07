<?php

declare(strict_types=1);

namespace App\Services\Calendar;

/**
 * Stage 3 F.2b/c — a calendar back-end that can mirror bookings as events.
 *
 * The normalized $event array is provider-agnostic:
 *   ['subject'=>string, 'description'=>string, 'location'=>string,
 *    'starts_at'=>string ISO-8601 UTC, 'ends_at'=>string ISO-8601 UTC]
 */
interface CalendarProvider
{
    public function key(): string;

    /**
     * @param  array<string, string>  $event
     * @return string external event id
     */
    public function createEvent(string $accessToken, ?string $calendarId, array $event): string;

    /**
     * @param  array<string, string>  $event
     */
    public function updateEvent(string $accessToken, ?string $calendarId, string $externalId, array $event): void;

    public function deleteEvent(string $accessToken, ?string $calendarId, string $externalId): void;
}
