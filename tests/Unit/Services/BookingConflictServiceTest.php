<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;

/**
 * Tests for App\Services\BookingConflictService.
 *
 * Each test maps 1:1 to a scenario in docs/sprint-2b-conflict-scenarios.md.
 * Implementation deliberately deferred to Phase 1; this class scaffolds
 * the test surface from the spec first (TDD red phase).
 *
 * @see docs/sprint-2b-conflict-scenarios.md
 */
class BookingConflictServiceTest extends TestCase
{
    // ─── EMPTY ROOM / OPERATING HOURS ───────────────────────────────

    /**
     * Scenario 1: Empty room during operating hours.
     * Room A active, Mon-Fri 08:00-17:00, no existing bookings.
     * Request: Mon 10:00-11:00 → Valid.
     */
    public function test_no_conflict_when_room_is_empty_during_operating_hours(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 4: Booking starts before operating hours open.
     * Mon 08:00-17:00, request Mon 07:00-09:00 → Invalid.
     */
    public function test_conflict_when_booking_starts_before_operating_hours_open(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 4b: Booking ends after operating hours close.
     * Mon 08:00-17:00, request Mon 16:00-18:00 → Invalid.
     */
    public function test_conflict_when_booking_ends_after_operating_hours_close(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 5: Booking on closed day.
     * Sunday is_closed=true, request Sunday 10:00-11:00 → Invalid.
     */
    public function test_conflict_when_booking_falls_on_closed_day(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    // ─── SLOT OVERLAP ───────────────────────────────────────────────

    /**
     * Scenario 6: Exact overlap with approved booking.
     * Existing approved 09:00-10:00, request 09:00-10:00 exact → Invalid.
     */
    public function test_conflict_on_exact_overlap_with_approved_booking(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 7: Request starts during existing booking.
     * Existing 09:00-10:00, request 09:30-10:30 → Invalid.
     */
    public function test_conflict_when_request_starts_during_existing_booking(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 8: Request ends during existing booking.
     * Existing 09:00-10:00, request 08:30-09:30 → Invalid.
     */
    public function test_conflict_when_request_ends_during_existing_booking(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    // ─── BUFFER MATH ────────────────────────────────────────────────

    /**
     * Scenario 9: Back-to-back with zero buffer.
     * Existing 09:00-10:00 buffer=0, request 10:00-11:00 → Valid.
     * Formula uses strict < and >, so touching boundaries are valid.
     */
    public function test_no_conflict_when_back_to_back_with_zero_buffer(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 10: Gap smaller than buffer.
     * Existing 09:00-10:00 buffer=15, request 10:10-11:00 → Invalid (10:00+15 > 10:10).
     */
    public function test_conflict_when_gap_smaller_than_buffer(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 11: Gap exactly equals buffer.
     * Existing 09:00-10:00 buffer=15, request 10:15-11:00 → Valid (10:15 > 10:15 = false).
     * BOUNDARY TEST — strict comparison matters.
     */
    public function test_no_conflict_when_gap_equals_buffer_exactly(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 12: Gap greater than buffer.
     * Existing 09:00-10:00 buffer=15, request 10:30-11:00 → Valid.
     */
    public function test_no_conflict_when_gap_greater_than_buffer(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    // ─── NON-LOCKING STATUSES ───────────────────────────────────────

    /**
     * Scenario 13: Overlap with draft booking.
     * Existing DRAFT 09:00-10:00 by user X, request 09:30-10:30 by user Y → Valid.
     */
    public function test_no_conflict_with_draft_status_booking(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 14: Overlap with rejected/cancelled bookings.
     * Both rejected and cancelled don't lock slots.
     */
    public function test_no_conflict_with_rejected_or_cancelled_bookings(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    // ─── ROOM BLOCKS ────────────────────────────────────────────────

    /**
     * Scenario 15: Overlap with active room block.
     * Block 14:00-16:00 (maintenance), request 13:00-15:00 → Invalid.
     */
    public function test_conflict_with_active_room_block(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 16: Overlap with cancelled room block.
     * Block 14:00-16:00 with cancelled_at set, request 13:00-15:00 → Valid.
     */
    public function test_no_conflict_with_cancelled_room_block(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    // ─── SERVICE PURITY ─────────────────────────────────────────────

    /**
     * Scenario 17: Service is role-agnostic.
     * No backdoor for "admin override" — that's Action-layer concern.
     */
    public function test_service_does_not_bypass_conflict_for_any_caller(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 22: All datetimes treated as UTC.
     * Per Dec-09, no implicit timezone shifts.
     */
    public function test_service_treats_all_datetimes_as_utc(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    // ─── SERVICE-SPECIFIC (beyond Blueprint M.2) ────────────────────

    /**
     * Scenario 23: excludeBookingId excludes self from conflict check.
     * Used by edit flow — when editing booking #42, don't conflict with #42.
     */
    public function test_exclude_booking_id_excludes_self_from_conflict_check(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 24: Buffer hierarchy — room buffer wins when explicit.
     * Room buffer=20, system default=15 → uses 20.
     */
    public function test_buffer_uses_room_value_when_room_has_explicit_buffer(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 25: Buffer hierarchy — system default fallback.
     * Room buffer=0, system default=15 → uses 15.
     */
    public function test_buffer_uses_system_default_when_room_buffer_is_zero(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 26: findConflicts returns multiple conflicts.
     * Request overlaps 2 bookings + 1 block → returns 3 items.
     */
    public function test_find_conflicts_returns_all_overlapping_items(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }

    /**
     * Scenario 27: assertNoConflict throws BookingConflictException.
     * Exception payload includes the conflicts found.
     */
    public function test_assert_no_conflict_throws_booking_conflict_exception_with_conflicts_payload(): void
    {
        $this->markTestIncomplete('Phase 0 placeholder — implementation in Phase 1');
    }
}
