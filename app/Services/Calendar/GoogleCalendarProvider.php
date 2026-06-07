<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use Illuminate\Support\Facades\Http;

/**
 * Google Calendar driver. Built to the documented Calendar v3 events REST
 * contract; activate once a GCP OAuth client / domain-wide delegation exists.
 */
class GoogleCalendarProvider implements CalendarProvider
{
    public function key(): string
    {
        return 'google';
    }

    public function createEvent(string $accessToken, ?string $calendarId, array $event): string
    {
        $response = Http::withToken($accessToken)
            ->post($this->collection($calendarId), $this->body($event))
            ->throw();

        return (string) $response->json('id');
    }

    public function updateEvent(string $accessToken, ?string $calendarId, string $externalId, array $event): void
    {
        Http::withToken($accessToken)
            ->patch($this->collection($calendarId).'/'.$externalId, $this->body($event))
            ->throw();
    }

    public function deleteEvent(string $accessToken, ?string $calendarId, string $externalId): void
    {
        Http::withToken($accessToken)
            ->delete($this->collection($calendarId).'/'.$externalId)
            ->throw();
    }

    private function collection(?string $calendarId): string
    {
        $base = rtrim((string) config('calendar.google.api_base'), '/');
        $cal = $calendarId === null || $calendarId === '' ? 'primary' : $calendarId;

        return $base.'/calendars/'.rawurlencode($cal).'/events';
    }

    /**
     * @param  array<string, string>  $event
     * @return array<string, mixed>
     */
    private function body(array $event): array
    {
        return [
            'summary' => $event['subject'],
            'description' => $event['description'],
            'location' => $event['location'],
            'start' => ['dateTime' => $event['starts_at'], 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $event['ends_at'], 'timeZone' => 'UTC'],
        ];
    }
}
