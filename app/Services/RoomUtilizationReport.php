<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Unit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Stage 2.1d — Room utilization analytics.
 *
 * Aggregates "actual usage" bookings (Approved + Completed) over a date range
 * and derives per-room utilization, peak-hour occupancy, and per-unit demand.
 *
 * Design notes:
 * - Bookings are stored in UTC (config app.timezone=UTC). All day/hour bucketing
 *   is done in the DISPLAY timezone (default Asia/Jakarta) so "peak hours" and
 *   "business days" reflect what staff actually experience.
 * - Computation happens in PHP over a minimal column set, NOT in SQL, so the
 *   report is portable across sqlite (tests) and mysql (prod) — no DB-specific
 *   date functions.
 * - "Capacity" per room = BUSINESS_HOURS_PER_DAY x weekdays-in-range. Utilization
 *   is booked-hours / capacity, capped for display by the caller if desired.
 */
class RoomUtilizationReport
{
    /** Start of the bookable business day, in the display timezone (08:00). */
    public const BUSINESS_START_HOUR = 8;

    /** End of the bookable business day, in the display timezone (17:00). */
    public const BUSINESS_END_HOUR = 17;

    /** Statuses that represent real room usage (not draft/cancelled/rejected). */
    private const ACTIVE_STATUSES = [BookingStatus::Approved, BookingStatus::Completed];

    public function __construct(private readonly string $displayTimezone = 'Asia/Jakarta') {}

    public function businessHoursPerDay(): int
    {
        return self::BUSINESS_END_HOUR - self::BUSINESS_START_HOUR;
    }

    /**
     * Build the full report for [$from 00:00 .. $to 23:59:59] in the display tz.
     *
     * @return array{
     *     range: array{from: string, to: string, weekdays: int, timezone: string},
     *     summary: array{active_bookings: int, total_bookings: int, cancelled: int,
     *         cancellation_rate: float, booked_hours: float, capacity_hours: float,
     *         utilization: float, rooms_with_activity: int, no_show: int,
     *         no_show_unreclaimed: int, no_show_rate: float},
     *     rooms: array<int, array{room_id: int, code: string, name: string,
     *         bookings: int, booked_hours: float, capacity_hours: float, utilization: float}>,
     *     peak_hours: array<int, array{hour: int, label: string, hours: float}>,
     *     units: array<int, array{unit_id: int|null, name: string, bookings: int, booked_hours: float}>
     * }
     */
    public function build(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $tz = $this->displayTimezone;

        $rangeStart = $from->setTimezone($tz)->startOfDay();
        $rangeEnd = $to->setTimezone($tz)->endOfDay();

        // Query window in UTC (stored timezone). A booking counts if it STARTS
        // within the range — keeps each booking attributed to a single day.
        $utcStart = $rangeStart->setTimezone('UTC');
        $utcEnd = $rangeEnd->setTimezone('UTC');

        /** @var Collection<int, Booking> $bookings */
        $bookings = Booking::query()
            ->whereBetween('starts_at', [$utcStart, $utcEnd])
            ->with(['room:id,code,name', 'requesterUnit:id,name'])
            ->get(['id', 'resource_id', 'requester_unit_id', 'starts_at', 'ends_at', 'status', 'checked_in_at', 'released_at']);

        $active = $bookings->filter(
            fn (Booking $b): bool => in_array($b->status, self::ACTIVE_STATUSES, strict: true)
        );

        $cancelled = $bookings->filter(
            fn (Booking $b): bool => $b->status === BookingStatus::Cancelled
        )->count();

        // No-show (A.2): auto-released bookings (released_at stamped) are confirmed
        // no-shows. "Unreclaimed" = an Approved meeting that has ended without a
        // check-in and was never auto-released (e.g. ended inside the grace window).
        $now = CarbonImmutable::now();
        $noShow = $bookings->filter(fn (Booking $b): bool => $b->released_at !== null)->count();
        $noShowUnreclaimed = $bookings->filter(
            fn (Booking $b): bool => $b->status === BookingStatus::Approved
                && $b->checked_in_at === null
                && $b->released_at === null
                && $b->ends_at < $now
        )->count();
        $noShowDenominator = $active->count() + $noShow;

        $weekdays = $this->weekdaysInRange($rangeStart, $rangeEnd);
        $capacityPerRoom = (float) ($weekdays * $this->businessHoursPerDay());

        $rooms = $this->perRoom($active, $capacityPerRoom);
        $peakHours = $this->peakHours($active, $tz);
        $units = $this->perUnit($active);

        $bookedHours = 0.0;
        foreach ($active as $booking) {
            $bookedHours += $this->durationHours($booking);
        }
        $bookedHours = round($bookedHours, 1);
        $roomsWithActivity = count($rooms);
        $capacityHours = round($capacityPerRoom * $roomsWithActivity, 1);

        return [
            'range' => [
                'from' => $rangeStart->format('Y-m-d'),
                'to' => $rangeEnd->format('Y-m-d'),
                'weekdays' => $weekdays,
                'timezone' => $tz,
            ],
            'summary' => [
                'active_bookings' => $active->count(),
                'total_bookings' => $bookings->count(),
                'cancelled' => $cancelled,
                'cancellation_rate' => $bookings->count() > 0
                    ? round($cancelled / $bookings->count() * 100, 1)
                    : 0.0,
                'booked_hours' => $bookedHours,
                'capacity_hours' => $capacityHours,
                'utilization' => $capacityHours > 0
                    ? round($bookedHours / $capacityHours * 100, 1)
                    : 0.0,
                'rooms_with_activity' => $roomsWithActivity,
                'no_show' => $noShow,
                'no_show_unreclaimed' => $noShowUnreclaimed,
                'no_show_rate' => $noShowDenominator > 0
                    ? round($noShow / $noShowDenominator * 100, 1)
                    : 0.0,
            ],
            'rooms' => $rooms,
            'peak_hours' => $peakHours,
            'units' => $units,
        ];
    }

    /**
     * @param  Collection<int, Booking>  $active
     * @return list<array{room_id: int, code: string, name: string, bookings: int, booked_hours: float, capacity_hours: float, utilization: float}>
     */
    private function perRoom(Collection $active, float $capacityPerRoom): array
    {
        /** @var array<int, array{room_id: int, code: string, name: string, bookings: int, booked_hours: float}> $acc */
        $acc = [];

        foreach ($active as $booking) {
            $roomId = (int) $booking->resource_id;
            if (! isset($acc[$roomId])) {
                $room = $booking->room;
                $acc[$roomId] = [
                    'room_id' => $roomId,
                    'code' => $room instanceof Room ? $room->code : '—',
                    'name' => $room instanceof Room ? $room->name : '—',
                    'bookings' => 0,
                    'booked_hours' => 0.0,
                ];
            }
            $acc[$roomId]['bookings']++;
            $acc[$roomId]['booked_hours'] += $this->durationHours($booking);
        }

        $rooms = [];
        foreach ($acc as $row) {
            $hours = round($row['booked_hours'], 1);
            $rooms[] = [
                'room_id' => $row['room_id'],
                'code' => $row['code'],
                'name' => $row['name'],
                'bookings' => $row['bookings'],
                'booked_hours' => $hours,
                'capacity_hours' => round($capacityPerRoom, 1),
                'utilization' => $capacityPerRoom > 0
                    ? round($hours / $capacityPerRoom * 100, 1)
                    : 0.0,
            ];
        }

        usort($rooms, fn (array $a, array $b): int => $b['utilization'] <=> $a['utilization']);

        return $rooms;
    }

    /**
     * Occupancy distribution by hour-of-day (display tz). Each booking contributes
     * the fraction of each hour bin it covers, so a 90-minute meeting adds 1.0h to
     * one bin and 0.5h to the next.
     *
     * @param  Collection<int, Booking>  $active
     * @return array<int, array{hour: int, label: string, hours: float}>
     */
    private function peakHours(Collection $active, string $tz): array
    {
        $bins = array_fill(0, 24, 0.0);

        foreach ($active as $booking) {
            $start = $booking->starts_at->toImmutable()->setTimezone($tz);
            $end = $booking->ends_at->toImmutable()->setTimezone($tz);

            if ($end <= $start) {
                continue;
            }

            $cursor = $start;
            // Walk hour boundaries, accumulating overlap into each hour bin.
            while ($cursor < $end) {
                $nextBoundary = $cursor->minute(0)->second(0)->addHour();
                $segmentEnd = $nextBoundary < $end ? $nextBoundary : $end;
                $hour = (int) $cursor->format('G');
                $bins[$hour] += ($segmentEnd->getTimestamp() - $cursor->getTimestamp()) / 3600;
                $cursor = $segmentEnd;
            }
        }

        $out = [];
        foreach ($bins as $hour => $hours) {
            $out[] = [
                'hour' => $hour,
                'label' => sprintf('%02d:00', $hour),
                'hours' => round($hours, 1),
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, Booking>  $active
     * @return list<array{unit_id: int|null, name: string, bookings: int, booked_hours: float}>
     */
    private function perUnit(Collection $active): array
    {
        /** @var array<int, array{unit_id: int|null, name: string, bookings: int, booked_hours: float}> $acc */
        $acc = [];

        foreach ($active as $booking) {
            $key = $booking->requester_unit_id ?? 0;
            if (! isset($acc[$key])) {
                $unit = $booking->requesterUnit;
                $acc[$key] = [
                    'unit_id' => $booking->requester_unit_id !== null ? (int) $booking->requester_unit_id : null,
                    'name' => $unit instanceof Unit ? $unit->name : 'Tanpa Unit',
                    'bookings' => 0,
                    'booked_hours' => 0.0,
                ];
            }
            $acc[$key]['bookings']++;
            $acc[$key]['booked_hours'] += $this->durationHours($booking);
        }

        $units = [];
        foreach ($acc as $row) {
            $row['booked_hours'] = round($row['booked_hours'], 1);
            $units[] = $row;
        }

        usort($units, fn (array $a, array $b): int => $b['booked_hours'] <=> $a['booked_hours']);

        return $units;
    }

    private function durationHours(Booking $booking): float
    {
        $seconds = $booking->ends_at->getTimestamp() - $booking->starts_at->getTimestamp();

        return $seconds > 0 ? $seconds / 3600 : 0.0;
    }

    private function weekdaysInRange(CarbonImmutable $start, CarbonImmutable $end): int
    {
        $count = 0;
        $cursor = $start->startOfDay();
        $last = $end->startOfDay();

        while ($cursor <= $last) {
            if (! $cursor->isWeekend()) {
                $count++;
            }
            $cursor = $cursor->addDay();
        }

        return $count;
    }
}
