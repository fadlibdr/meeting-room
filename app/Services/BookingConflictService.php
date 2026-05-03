<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ConflictItem;
use App\Exceptions\BookingConflictException;
use App\Models\Room;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Read-only service that detects time/space conflicts for a proposed booking slot.
 *
 * Scope (per Sprint 2B Phase 0 architectural decisions):
 * - Slot overlap with existing locking-status bookings (with buffer math)
 * - Slot overlap with active room blocks (no buffer)
 * - Operating hours violations
 *
 * Out of scope (handled elsewhere):
 * - Input shape validation (ends_at > starts_at, max duration) → Form Request
 * - Capacity validation → Form Request
 * - Retrospective booking authorization → Policy
 * - Race condition prevention → Caller wraps in DB::transaction + lockForUpdate
 *
 * @see docs/sprint-2b-conflict-scenarios.md for authoritative test spec
 */
final class BookingConflictService
{
    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Find all conflicts for a proposed booking slot.
     *
     * @param  Room  $room  The room being booked
     * @param  CarbonInterface  $startsAt  Proposed start (UTC)
     * @param  CarbonInterface  $endsAt  Proposed end (UTC)
     * @param  ?int  $excludeBookingId  Optional booking ID to exclude (for edit case — booking shouldn't conflict with itself)
     * @return Collection<int, ConflictItem>
     */
    public function findConflicts(
        Room $room,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $excludeBookingId = null,
    ): Collection {
        // Phase 1: scenarios will be implemented one at a time.
        // Skeleton returns empty collection so assertNoConflict works
        // for the trivial "no conflicts" case.
        return collect();
    }

    /**
     * Assert that there are no conflicts; throw if there are.
     *
     * @throws BookingConflictException When conflicts exist
     */
    public function assertNoConflict(
        Room $room,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $excludeBookingId = null,
    ): void {
        $conflicts = $this->findConflicts($room, $startsAt, $endsAt, $excludeBookingId);

        if ($conflicts->isNotEmpty()) {
            throw new BookingConflictException($conflicts);
        }
    }

    /**
     * Resolve effective buffer minutes for a room (Dec-10b hierarchy):
     * - room.booking_buffer_minutes > 0 → use room's value
     * - room.booking_buffer_minutes = 0 → use system default from app_settings
     *
     * @phpstan-ignore method.unused (will be used by findConflicts in Phase 1 buffer scenarios)
     */
    private function effectiveBufferMinutes(Room $room): int
    {
        if ($room->booking_buffer_minutes > 0) {
            return $room->booking_buffer_minutes;
        }

        $default = $this->settings->get('booking.default_buffer_minutes', 15);

        return is_numeric($default) ? (int) $default : 15;
    }
}
