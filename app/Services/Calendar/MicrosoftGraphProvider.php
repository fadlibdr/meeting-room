<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use Illuminate\Support\Facades\Http;

/**
 * Microsoft Graph calendar driver (Outlook / M365). Built to the documented
 * Graph v1.0 /events REST contract; activate once an Entra app + Calendars
 * permission exist.
 */
class MicrosoftGraphProvider implements CalendarProvider
{
    public function key(): string
    {
        return 'microsoft';
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
        $base = rtrim((string) config('calendar.microsoft.graph_base'), '/');

        return $calendarId === null || $calendarId === ''
            ? $base.'/me/events'
            : $base.'/me/calendars/'.$calendarId.'/events';
    }

    /**
     * @param  array<string, string>  $event
     * @return array<string, mixed>
     */
    private function body(array $event): array
    {
        return [
            'subject' => $event['subject'],
            'body' => ['contentType' => 'text', 'content' => $event['description']],
            'location' => ['displayName' => $event['location']],
            'start' => ['dateTime' => $event['starts_at'], 'timeZone' => 'UTC'],
            'end' => ['dateTime' => $event['ends_at'], 'timeZone' => 'UTC'],
        ];
    }
}
