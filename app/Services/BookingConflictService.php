<?php

declare(strict_types=1);

namespace App\Services;

use App\DataTransferObjects\ConflictItem;
use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\RoomOperatingHour;
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
 * Limitations:
 * - Single-day requests assumed. Cross-midnight requests are not validated
 *   against operating hours correctly. This matches BPJS reality (internal
 *   meetings during business hours) but should be revisited if multi-day
 *   bookings become a requirement.
 *
 * @see docs/sprint-2b-conflict-scenarios.md for authoritative test spec
 */
final class BookingConflictService
{
    /**
     * Booking statuses that lock a slot from being booked again.
     * Per Blueprint H.3, these are the statuses that count as "occupying" the room.
     */
    private const LOCKING_STATUSES = [
        BookingStatus::Submitted,
        BookingStatus::Approved,
    ];

    public function __construct(
        private readonly SettingsService $settings,
    ) {}

    /**
     * Find all conflicts for a proposed booking slot.
     *
     * @param  Room  $room  The room being booked
     * @param  CarbonInterface  $startsAt  Proposed start (UTC)
     * @param  CarbonInterface  $endsAt  Proposed end (UTC)
     * @param  ?int  $excludeBookingId  Optional booking ID to exclude (for edit case)
     * @return Collection<int, ConflictItem>
     */
    public function findConflicts(
        Room $room,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $excludeBookingId = null,
    ): Collection {
        $bookingConflicts = $this->findBookingConflicts($room, $startsAt, $endsAt, $excludeBookingId);
        $blockConflicts = $this->findBlockConflicts($room, $startsAt, $endsAt);
        $operatingHoursConflicts = $this->findOperatingHoursConflicts($room, $startsAt, $endsAt);

        return collect([...$bookingConflicts, ...$blockConflicts, ...$operatingHoursConflicts]);
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
     * Find bookings that overlap the requested slot, accounting for buffer.
     *
     * Formula (Blueprint H.3):
     *   existing.starts_at < requested.ends_at
     *   AND (existing.ends_at + buffer) > requested.starts_at
     *
     * Only locking statuses (submitted, approved) are considered.
     *
     * @return array<int, ConflictItem>
     */
    private function findBookingConflicts(
        Room $room,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?int $excludeBookingId,
    ): array {
        $bufferMinutes = $this->effectiveBufferMinutes($room);

        $query = Booking::query()
            ->where('room_id', $room->id)
            ->whereIn('status', array_map(fn (BookingStatus $s) => $s->value, self::LOCKING_STATUSES))
            ->where('starts_at', '<', $endsAt)
            ->whereRaw(
                'DATE_ADD(ends_at, INTERVAL ? MINUTE) > ?',
                [$bufferMinutes, $startsAt->format('Y-m-d H:i:s')]
            );

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return $query->get()->map(fn (Booking $b) => new ConflictItem(
            type: ConflictItem::TYPE_BOOKING,
            title: $b->subject,
            startsAt: $b->starts_at,
            endsAt: $b->ends_at,
            reference: $b,
        ))->all();
    }

    /**
     * Find active room blocks that overlap the requested slot.
     *
     * Formula (Blueprint H.3 — no buffer for blocks):
     *   block.starts_at < requested.ends_at AND block.ends_at > requested.starts_at
     *
     * @return array<int, ConflictItem>
     */
    private function findBlockConflicts(
        Room $room,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): array {
        return RoomBlockSchedule::query()
            ->where('room_id', $room->id)
            ->where('is_active', true)
            ->whereNull('cancelled_at')
            ->where('starts_at', '<', $endsAt)
            ->where('ends_at', '>', $startsAt)
            ->get()
            ->map(fn (RoomBlockSchedule $b) => new ConflictItem(
                type: ConflictItem::TYPE_BLOCK,
                title: $b->title,
                startsAt: $b->starts_at,
                endsAt: $b->ends_at,
                reference: $b,
            ))
            ->all();
    }

    /**
     * Check if request falls outside the room's operating hours for that day.
     *
     * Three failure modes:
     * 1. Day is closed (is_closed=true)
     * 2. Request starts before open_time on that day
     * 3. Request ends after close_time on that day
     *
     * If no operating_hours row exists for that day, the room is effectively
     * always-closed (defensive default — don't allow bookings when hours unknown).
     *
     * @return array<int, ConflictItem>
     */
    private function findOperatingHoursConflicts(
        Room $room,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
    ): array {
        $dayOfWeek = $startsAt->dayOfWeek;  // Carbon: 0=Sunday, 1=Monday, ..., 6=Saturday

        /** @var RoomOperatingHour|null $hours */
        $hours = RoomOperatingHour::query()
            ->where('room_id', $room->id)
            ->where('day_of_week', $dayOfWeek)
            ->first();

        // No row at all → room hasn't been configured; default to no conflict
        // (admin should set hours; absence of config shouldn't block bookings)
        if ($hours === null) {
            return [];
        }

        // Closed day → conflict
        if ($hours->is_closed) {
            return [new ConflictItem(
                type: ConflictItem::TYPE_OPERATING_HOURS,
                title: 'Ruangan tutup pada hari ini',
                startsAt: $startsAt,
                endsAt: $endsAt,
                reference: null,
            )];
        }

        // Open/close time check — compare time-of-day only (HH:MM:SS strings)
        $requestStartTime = $startsAt->format('H:i:s');
        $requestEndTime = $endsAt->format('H:i:s');

        if ($hours->open_time !== null && $requestStartTime < $hours->open_time) {
            return [new ConflictItem(
                type: ConflictItem::TYPE_OPERATING_HOURS,
                title: 'Booking dimulai sebelum jam buka ruangan',
                startsAt: $startsAt,
                endsAt: $endsAt,
                reference: null,
            )];
        }

        if ($hours->close_time !== null && $requestEndTime > $hours->close_time) {
            return [new ConflictItem(
                type: ConflictItem::TYPE_OPERATING_HOURS,
                title: 'Booking berakhir setelah jam tutup ruangan',
                startsAt: $startsAt,
                endsAt: $endsAt,
                reference: null,
            )];
        }

        return [];
    }

    /**
     * Resolve effective buffer minutes for a room (Dec-10b hierarchy):
     * - room.booking_buffer_minutes > 0 → use room's value
     * - room.booking_buffer_minutes = 0 → use system default from app_settings
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
