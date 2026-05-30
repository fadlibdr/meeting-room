<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Uniform representation of a single conflict detected by
 * BookingConflictService. Used by UI to render conflict lists
 * (e.g. BookingForm shows these as "why this slot won't work").
 *
 * Three conflict types currently:
 * - 'booking' — overlap with existing locking-status booking
 * - 'block' — overlap with active room_block_schedule
 * - 'operating_hours' — request falls outside room's operating hours
 *
 * The reference field is optional and points to the originating model
 * (Booking or RoomBlockSchedule). Operating hour conflicts have null
 * reference because they are derived from rules, not a row.
 */
final readonly class ConflictItem
{
    public const TYPE_BOOKING = 'booking';

    public const TYPE_BLOCK = 'block';

    public const TYPE_OPERATING_HOURS = 'operating_hours';

    public function __construct(
        public string $type,
        public string $title,
        public CarbonInterface $startsAt,
        public CarbonInterface $endsAt,
        public ?Model $reference = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'starts_at' => $this->startsAt->toIso8601String(),
            'ends_at' => $this->endsAt->toIso8601String(),
            'reference_id' => $this->reference?->getKey(),
        ];
    }
}
