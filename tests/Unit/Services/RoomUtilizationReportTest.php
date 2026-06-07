<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Models\Unit;
use App\Services\RoomUtilizationReport;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomUtilizationReportTest extends TestCase
{
    use RefreshDatabase;

    private function report(): RoomUtilizationReport
    {
        return new RoomUtilizationReport('Asia/Jakarta');
    }

    /**
     * 2026-06-08 is a Monday. A 09:00–11:00 WIB meeting is stored as 02:00–04:00 UTC.
     */
    private function approvedBooking(Room $room, Unit $unit, string $utcStart, string $utcEnd, string $status = 'approved'): Booking
    {
        return Booking::factory()->create([
            'resource_id' => $room->id,
            'requester_unit_id' => $unit->id,
            'status' => $status,
            'starts_at' => $utcStart,
            'ends_at' => $utcEnd,
        ]);
    }

    public function test_no_show_metric_counts_auto_released_bookings(): void
    {
        $room = Room::factory()->create();
        $unit = Unit::factory()->create();

        // One meeting that happened (active) ...
        $this->approvedBooking($room, $unit, '2026-06-08 02:00:00', '2026-06-08 03:00:00', 'approved');
        // ... and one auto-released no-show (Cancelled + released_at stamped).
        Booking::factory()->create([
            'resource_id' => $room->id,
            'requester_unit_id' => $unit->id,
            'status' => 'cancelled',
            'starts_at' => '2026-06-08 05:00:00',
            'ends_at' => '2026-06-08 06:00:00',
            'released_at' => '2026-06-08 05:15:00',
        ]);

        $day = CarbonImmutable::parse('2026-06-08', 'Asia/Jakarta');
        $summary = $this->report()->build($day, $day)['summary'];

        $this->assertSame(1, $summary['no_show']);
        // rate = released / (active + released) = 1 / (1 + 1) = 50%.
        $this->assertSame(50.0, $summary['no_show_rate']);
        $this->assertSame(0, $summary['no_show_unreclaimed']); // range is in the future
    }

    public function test_per_room_utilization_uses_business_hour_capacity(): void
    {
        $room = Room::factory()->create();
        $unit = Unit::factory()->create();
        // 09:00–11:00 WIB = 02:00–04:00 UTC, 2 hours, on Monday.
        $this->approvedBooking($room, $unit, '2026-06-08 02:00:00', '2026-06-08 04:00:00');

        $day = CarbonImmutable::parse('2026-06-08', 'Asia/Jakarta');
        $result = $this->report()->build($day, $day);

        // One weekday in range → capacity = 9 business hours.
        $this->assertSame(1, $result['range']['weekdays']);
        $this->assertSame(2.0, $result['summary']['booked_hours']);
        $this->assertSame(9.0, $result['summary']['capacity_hours']);
        $this->assertSame(22.2, $result['summary']['utilization']); // 2/9

        $this->assertCount(1, $result['rooms']);
        $this->assertSame($room->id, $result['rooms'][0]['room_id']);
        $this->assertSame(2.0, $result['rooms'][0]['booked_hours']);
        $this->assertSame(22.2, $result['rooms'][0]['utilization']);
    }

    public function test_peak_hours_bucket_in_display_timezone(): void
    {
        $room = Room::factory()->create();
        $unit = Unit::factory()->create();
        $this->approvedBooking($room, $unit, '2026-06-08 02:00:00', '2026-06-08 04:00:00');

        $day = CarbonImmutable::parse('2026-06-08', 'Asia/Jakarta');
        $result = $this->report()->build($day, $day);

        $byHour = collect($result['peak_hours'])->keyBy('hour');
        // 09:00–11:00 WIB → one hour each in bins 9 and 10, nothing in UTC-hour 2/3.
        $this->assertSame(1.0, $byHour[9]['hours']);
        $this->assertSame(1.0, $byHour[10]['hours']);
        $this->assertSame(0.0, $byHour[2]['hours']);
        $this->assertSame(0.0, $byHour[11]['hours']);
    }

    public function test_cancelled_and_draft_excluded_from_active_but_counted_in_totals(): void
    {
        $room = Room::factory()->create();
        $unit = Unit::factory()->create();
        $this->approvedBooking($room, $unit, '2026-06-08 02:00:00', '2026-06-08 04:00:00', 'approved');
        $this->approvedBooking($room, $unit, '2026-06-08 05:00:00', '2026-06-08 06:00:00', 'cancelled');

        $day = CarbonImmutable::parse('2026-06-08', 'Asia/Jakarta');
        $result = $this->report()->build($day, $day);

        $this->assertSame(1, $result['summary']['active_bookings']);
        $this->assertSame(2, $result['summary']['total_bookings']);
        $this->assertSame(1, $result['summary']['cancelled']);
        $this->assertSame(50.0, $result['summary']['cancellation_rate']);
        // Only the approved 2h counts toward usage.
        $this->assertSame(2.0, $result['summary']['booked_hours']);
    }

    public function test_completed_status_counts_as_usage(): void
    {
        $room = Room::factory()->create();
        $unit = Unit::factory()->create();
        $this->approvedBooking($room, $unit, '2026-06-08 02:00:00', '2026-06-08 03:00:00', 'completed');

        $day = CarbonImmutable::parse('2026-06-08', 'Asia/Jakarta');
        $result = $this->report()->build($day, $day);

        $this->assertSame(1, $result['summary']['active_bookings']);
        $this->assertSame(1.0, $result['summary']['booked_hours']);
    }

    public function test_bookings_outside_range_are_excluded(): void
    {
        $room = Room::factory()->create();
        $unit = Unit::factory()->create();
        // In range (Monday 2026-06-08) and clearly out of range (a week earlier).
        $this->approvedBooking($room, $unit, '2026-06-08 02:00:00', '2026-06-08 03:00:00');
        $this->approvedBooking($room, $unit, '2026-06-01 02:00:00', '2026-06-01 03:00:00');

        $day = CarbonImmutable::parse('2026-06-08', 'Asia/Jakarta');
        $result = $this->report()->build($day, $day);

        $this->assertSame(1, $result['summary']['active_bookings']);
        $this->assertSame(1.0, $result['summary']['booked_hours']);
    }

    public function test_per_unit_demand_groups_and_sorts_by_hours(): void
    {
        $room = Room::factory()->create();
        $busy = Unit::factory()->create(['name' => 'Unit Sibuk']);
        $quiet = Unit::factory()->create(['name' => 'Unit Sepi']);

        $this->approvedBooking($room, $busy, '2026-06-08 02:00:00', '2026-06-08 05:00:00'); // 3h
        $this->approvedBooking($room, $quiet, '2026-06-08 06:00:00', '2026-06-08 07:00:00'); // 1h

        $day = CarbonImmutable::parse('2026-06-08', 'Asia/Jakarta');
        $result = $this->report()->build($day, $day);

        $this->assertCount(2, $result['units']);
        // Sorted by booked_hours desc → busy unit first.
        $this->assertSame('Unit Sibuk', $result['units'][0]['name']);
        $this->assertSame(3.0, $result['units'][0]['booked_hours']);
        $this->assertSame('Unit Sepi', $result['units'][1]['name']);
    }

    public function test_weekend_only_range_has_zero_capacity_and_no_divide_by_zero(): void
    {
        // 2026-06-13 (Sat) – 2026-06-14 (Sun): no weekdays.
        $from = CarbonImmutable::parse('2026-06-13', 'Asia/Jakarta');
        $to = CarbonImmutable::parse('2026-06-14', 'Asia/Jakarta');

        $result = $this->report()->build($from, $to);

        $this->assertSame(0, $result['range']['weekdays']);
        $this->assertSame(0.0, $result['summary']['utilization']);
        $this->assertSame(0.0, $result['summary']['cancellation_rate']);
    }
}
