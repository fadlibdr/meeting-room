<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Exceptions\BookingConflictException;
use App\Models\AppSetting;
use App\Services\BookingConflictService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

/**
 * Tests for App\Services\BookingConflictService.
 *
 * Each test maps 1:1 to a scenario in docs/sprint-2b-conflict-scenarios.md.
 * Implementation deliberately TDD-driven: tests first, minimum service code
 * to make green, refactor.
 *
 * @see docs/sprint-2b-conflict-scenarios.md
 */
class BookingConflictServiceTest extends TestCase
{
    use CreatesBookingScenarios;
    use RefreshDatabase;

    private BookingConflictService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BookingConflictService::class);
    }

    // ─── EMPTY ROOM / OPERATING HOURS ───────────────────────────────

    /**
     * Scenario 1: Empty room during operating hours.
     * Mon 10:00-11:00 in a room with no bookings → Valid.
     */
    public function test_no_conflict_when_room_is_empty_during_operating_hours(): void
    {
        $room = $this->createRoomWithStandardHours();

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 10:00:00'),  // Monday
            $this->utc('2026-05-04 11:00:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    /**
     * Scenario 4: Booking starts before operating hours open.
     */
    public function test_conflict_when_booking_starts_before_operating_hours_open(): void
    {
        $room = $this->createRoomWithStandardHours();

        // Monday 07:00-09:00 — but Mon-Fri operating hours are 08:00-17:00
        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 07:00:00'),
            $this->utc('2026-05-04 09:00:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('operating_hours', $conflicts->first()->type);
    }

    /**
     * Scenario 4b: Booking ends after operating hours close.
     */
    public function test_conflict_when_booking_ends_after_operating_hours_close(): void
    {
        $room = $this->createRoomWithStandardHours();

        // Monday 16:00-18:00 — close is at 17:00
        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 16:00:00'),
            $this->utc('2026-05-04 18:00:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('operating_hours', $conflicts->first()->type);
    }

    /**
     * Scenario 5: Booking on closed day.
     */
    public function test_conflict_when_booking_falls_on_closed_day(): void
    {
        $room = $this->createRoomWithStandardHours();

        // Sunday is closed (createRoomWithStandardHours sets day_of_week=0 to is_closed=true)
        // 2026-05-03 is a Sunday
        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-03 10:00:00'),
            $this->utc('2026-05-03 11:00:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('operating_hours', $conflicts->first()->type);
    }

    // ─── SLOT OVERLAP ───────────────────────────────────────────────

    /**
     * Scenario 6: Exact overlap with approved booking.
     */
    public function test_conflict_on_exact_overlap_with_approved_booking(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 09:00:00'),
            $this->utc('2026-05-04 10:00:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('booking', $conflicts->first()->type);
    }

    /**
     * Scenario 7: Request starts during existing booking.
     */
    public function test_conflict_when_request_starts_during_existing_booking(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 09:30:00'),
            $this->utc('2026-05-04 10:30:00'),
        );

        $this->assertCount(1, $conflicts);
    }

    /**
     * Scenario 8: Request ends during existing booking.
     */
    public function test_conflict_when_request_ends_during_existing_booking(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 08:30:00'),
            $this->utc('2026-05-04 09:30:00'),
        );

        $this->assertCount(1, $conflicts);
    }

    // ─── BUFFER MATH ────────────────────────────────────────────────

    /**
     * Scenario 9: Back-to-back with zero buffer.
     */
    public function test_no_conflict_when_back_to_back_with_zero_buffer(): void
    {
        $room = $this->createRoomWithStandardHours(bufferMinutes: 0);
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 10:00:00'),
            $this->utc('2026-05-04 11:00:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    /**
     * Scenario 10: Gap smaller than buffer.
     * Existing 09:00-10:00 buffer=15, request 10:10-11:00 → conflict (10:00+15 = 10:15 > 10:10).
     */
    public function test_conflict_when_gap_smaller_than_buffer(): void
    {
        $room = $this->createRoomWithStandardHours(bufferMinutes: 15);
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 10:10:00'),
            $this->utc('2026-05-04 11:00:00'),
        );

        $this->assertCount(1, $conflicts);
    }

    /**
     * Scenario 11: Gap exactly equals buffer.
     * Existing 09:00-10:00 buffer=15, request 10:15-11:00 → no conflict.
     * BOUNDARY TEST — formula uses strict > so 10:15 > 10:15 is false (no conflict).
     */
    public function test_no_conflict_when_gap_equals_buffer_exactly(): void
    {
        $room = $this->createRoomWithStandardHours(bufferMinutes: 15);
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 10:15:00'),
            $this->utc('2026-05-04 11:00:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    /**
     * Scenario 12: Gap greater than buffer.
     * Existing 09:00-10:00 buffer=15, request 10:30-11:00 → no conflict.
     */
    public function test_no_conflict_when_gap_greater_than_buffer(): void
    {
        $room = $this->createRoomWithStandardHours(bufferMinutes: 15);
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 10:30:00'),
            $this->utc('2026-05-04 11:00:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    // ─── NON-LOCKING STATUSES ───────────────────────────────────────

    /**
     * Scenario 13: Overlap with draft booking.
     */
    public function test_no_conflict_with_draft_status_booking(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'draft');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 09:30:00'),
            $this->utc('2026-05-04 10:30:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    /**
     * Scenario 14: Overlap with rejected/cancelled bookings.
     */
    public function test_no_conflict_with_rejected_or_cancelled_bookings(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'rejected');
        $this->createBooking($room, '2026-05-04 11:00:00', '2026-05-04 12:00:00', 'cancelled');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 09:00:00'),
            $this->utc('2026-05-04 12:00:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    // ─── ROOM BLOCKS ────────────────────────────────────────────────

    /**
     * Scenario 15: Overlap with active room block.
     * Block 14:00-16:00 (maintenance), request 13:00-15:00 → conflict.
     */
    public function test_conflict_with_active_room_block(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBlock($room, '2026-05-04 14:00:00', '2026-05-04 16:00:00');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 13:00:00'),
            $this->utc('2026-05-04 15:00:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('block', $conflicts->first()->type);
    }

    /**
     * Scenario 16: Overlap with cancelled room block.
     * Block 14:00-16:00 with cancelled_at set, request 13:00-15:00 → no conflict.
     */
    public function test_no_conflict_with_cancelled_room_block(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBlock($room, '2026-05-04 14:00:00', '2026-05-04 16:00:00', cancelled: true);

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 13:00:00'),
            $this->utc('2026-05-04 15:00:00'),
        );

        $this->assertCount(0, $conflicts);
    }

    // ─── SERVICE PURITY ─────────────────────────────────────────────

    /**
     * Scenario 17: Service is role-agnostic.
     */
    public function test_service_does_not_bypass_conflict_for_any_caller(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 09:00:00'),
            $this->utc('2026-05-04 10:00:00'),
        );

        $this->assertCount(1, $conflicts);
    }

    /**
     * Scenario 22: All datetimes treated as UTC.
     */
    public function test_service_treats_all_datetimes_as_utc(): void
    {
        $room = $this->createRoomWithStandardHours();
        $existing = $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $this->assertSame('UTC', $existing->starts_at->timezone->getName());
        $this->assertSame('2026-05-04 09:00:00', $existing->starts_at->format('Y-m-d H:i:s'));

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 09:00:00'),
            $this->utc('2026-05-04 10:00:00'),
        );

        $this->assertCount(1, $conflicts);
        $this->assertSame('UTC', $conflicts->first()->startsAt->timezone->getName());
    }

    // ─── SERVICE-SPECIFIC ───────────────────────────────────────────

    /**
     * Scenario 23: excludeBookingId excludes self from conflict check.
     */
    public function test_exclude_booking_id_excludes_self_from_conflict_check(): void
    {
        $room = $this->createRoomWithStandardHours();
        $existing = $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 09:00:00'),
            $this->utc('2026-05-04 10:00:00'),
            excludeBookingId: $existing->id,
        );

        $this->assertCount(0, $conflicts);
    }

    /**
     * Scenario 24: Buffer hierarchy — room buffer wins when explicit.
     * Room buffer=20, system default=15 (from seeded settings).
     * Existing 09:00-10:00, request 10:18-11:00 (gap=18 min).
     * If room buffer (20) is used: 10:00+20=10:20 > 10:18 → conflict.
     * If system default (15) used: 10:00+15=10:15 > 10:18 is false → no conflict.
     * Expected: conflict (room buffer wins).
     */
    public function test_buffer_uses_room_value_when_room_has_explicit_buffer(): void
    {
        // Seed settings so the system default exists for fallback comparison
        AppSetting::factory()->create([
            'key' => 'booking.default_buffer_minutes',
            'value' => '15',
            'data_type' => 'integer',
            'label' => 'Default buffer',
            'group' => 'booking',
        ]);

        $room = $this->createRoomWithStandardHours(bufferMinutes: 20);
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 10:18:00'),
            $this->utc('2026-05-04 11:00:00'),
        );

        $this->assertCount(1, $conflicts);
    }

    /**
     * Scenario 25: Buffer hierarchy — system default fallback.
     * Room buffer=0, system default=15.
     * Existing 09:00-10:00, request 10:10-11:00 (gap=10 min).
     * Without fallback (buffer=0): 10:00+0=10:00 > 10:10 false → no conflict (wrong).
     * With fallback (buffer=15): 10:00+15=10:15 > 10:10 true → conflict (correct).
     * Expected: conflict (system default applied).
     */
    public function test_buffer_uses_system_default_when_room_buffer_is_zero(): void
    {
        AppSetting::factory()->create([
            'key' => 'booking.default_buffer_minutes',
            'value' => '15',
            'data_type' => 'integer',
            'label' => 'Default buffer',
            'group' => 'booking',
        ]);

        $room = $this->createRoomWithStandardHours(bufferMinutes: 0);
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 10:10:00'),
            $this->utc('2026-05-04 11:00:00'),
        );

        $this->assertCount(1, $conflicts);
    }

    /**
     * Scenario 26: findConflicts returns multiple conflicts.
     */
    public function test_find_conflicts_returns_all_overlapping_items(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');
        $this->createBooking($room, '2026-05-04 11:00:00', '2026-05-04 12:00:00', 'submitted');
        $this->createBlock($room, '2026-05-04 14:00:00', '2026-05-04 15:00:00');

        $conflicts = $this->service->findConflicts(
            $room,
            $this->utc('2026-05-04 09:00:00'),
            $this->utc('2026-05-04 15:00:00'),
        );

        $this->assertCount(3, $conflicts);
        $types = $conflicts->pluck('type')->sort()->values()->all();
        $this->assertSame(['block', 'booking', 'booking'], $types);
    }

    /**
     * Scenario 27: assertNoConflict throws BookingConflictException.
     */
    public function test_assert_no_conflict_throws_booking_conflict_exception_with_conflicts_payload(): void
    {
        $room = $this->createRoomWithStandardHours();
        $this->createBooking($room, '2026-05-04 09:00:00', '2026-05-04 10:00:00', 'approved');

        $thrown = false;
        try {
            $this->service->assertNoConflict(
                $room,
                $this->utc('2026-05-04 09:00:00'),
                $this->utc('2026-05-04 10:00:00'),
            );
        } catch (BookingConflictException $e) {
            $thrown = true;
            $this->assertCount(1, $e->conflicts);
            $this->assertSame('booking', $e->conflicts->first()->type);
        }

        $this->assertTrue($thrown, 'Expected BookingConflictException to be thrown.');
    }
}
