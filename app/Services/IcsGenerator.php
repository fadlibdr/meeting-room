<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Models\Resource;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Generates an RFC 5545 iCalendar (.ics) document for a booking.
 *
 * Times are emitted as UTC (`...Z`) — bookings are stored UTC (Dec-09), so no
 * VTIMEZONE is needed; every calendar client converts to the viewer's zone.
 * Text values are escaped per §3.3.11 and content lines are folded at 75
 * octets on UTF-8-safe boundaries (§3.1).
 */
final class IcsGenerator
{
    public function forBooking(Booking $booking): string
    {
        return $this->wrap($this->vevent($booking));
    }

    /**
     * A subscribable feed (RFC 5545) of many bookings — one VEVENT each.
     *
     * @param  iterable<Booking>  $bookings
     */
    public function forFeed(iterable $bookings, string $calendarName): string
    {
        $events = [];
        foreach ($bookings as $booking) {
            array_push($events, ...$this->vevent($booking));
        }

        return $this->wrap($events, $calendarName);
    }

    /**
     * @param  list<string>  $eventLines
     */
    private function wrap(array $eventLines, ?string $calendarName = null): string
    {
        $head = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//BPJS Kesehatan//Meeting Room//ID',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        if ($calendarName !== null) {
            $head[] = 'X-WR-CALNAME:'.$this->escape($calendarName);
        }

        $lines = [...$head, ...$eventLines, 'END:VCALENDAR'];

        return implode("\r\n", array_map(fn (string $line): string => $this->fold($line), $lines))."\r\n";
    }

    /**
     * @return list<string>
     */
    private function vevent(Booking $booking): array
    {
        return [
            'BEGIN:VEVENT',
            'UID:'.$this->uid($booking),
            'DTSTAMP:'.$this->utc(Carbon::now()),
            'DTSTART:'.$this->utc($booking->starts_at),
            'DTEND:'.$this->utc($booking->ends_at),
            'SUMMARY:'.$this->escape($booking->subject),
            'DESCRIPTION:'.$this->escape($this->description($booking)),
            'LOCATION:'.$this->escape($this->location($booking)),
            'STATUS:CONFIRMED',
            'END:VEVENT',
        ];
    }

    public function filename(Booking $booking): string
    {
        return 'reservasi-'.$booking->booking_code.'.ics';
    }

    private function utc(CarbonInterface $dt): string
    {
        return $dt->copy()->utc()->format('Ymd\THis\Z');
    }

    private function uid(Booking $booking): string
    {
        $host = parse_url((string) config('app.url', 'http://localhost'), PHP_URL_HOST) ?: 'meeting-room';

        return 'booking-'.$booking->id.'-'.$booking->booking_code.'@'.$host;
    }

    private function description(Booking $booking): string
    {
        $parts = ['Kode Reservasi: '.$booking->booking_code];
        if ($booking->agenda !== null && $booking->agenda !== '') {
            $parts[] = $booking->agenda;
        }

        return implode("\n", $parts);
    }

    private function location(Booking $booking): string
    {
        $resource = $booking->resource;
        if (! $resource instanceof Resource) {
            return '-';
        }

        return trim($resource->name.($resource->location !== null && $resource->location !== '' ? ' — '.$resource->location : ''));
    }

    /**
     * Escape TEXT values per RFC 5545 §3.3.11 (backslash first).
     */
    private function escape(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $text,
        );
    }

    /**
     * Fold a content line at 75 octets, never splitting a UTF-8 multibyte
     * character; continuation lines begin with a single space (§3.1).
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $result = '';
        $octets = 0;
        foreach (mb_str_split($line) as $char) {
            $charLen = strlen($char);
            if ($octets + $charLen > 75) {
                $result .= "\r\n ";
                $octets = 1; // the leading space
            }
            $result .= $char;
            $octets += $charLen;
        }

        return $result;
    }
}
