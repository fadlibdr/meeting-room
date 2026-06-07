<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\RoomOperatingHour;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Test helpers for BookingConflictService scenarios.
 *
 * All datetime methods produce UTC CarbonImmutable instances per Dec-09.
 * All factory helpers produce models with explicit, predictable data
 * so individual scenarios can verify exact behavior.
 */
trait CreatesBookingScenarios
{
    /**
     * Create a UTC CarbonImmutable from "Y-m-d H:i:s" string.
     */
    protected function utc(string $datetime): CarbonImmutable
    {
        return CarbonImmutable::parse($datetime, 'UTC');
    }

    /**
     * Create a room with standard operating hours:
     * - Mon-Fri 08:00-17:00 (open)
     * - Sat 09:00-13:00 (open)
     * - Sun closed
     *
     * day_of_week convention: 0=Sunday, 1=Monday, ..., 6=Saturday (Carbon)
     */
    protected function createRoomWithStandardHours(int $bufferMinutes = 0): Room
    {
        $room = Room::factory()->create([
            'booking_buffer_minutes' => $bufferMinutes,
        ]);

        // Sunday closed
        RoomOperatingHour::factory()->create([
            'room_id' => $room->id,
            'day_of_week' => 0,
            'is_closed' => true,
            'open_time' => null,
            'close_time' => null,
        ]);

        // Mon-Fri 08:00-17:00
        for ($day = 1; $day <= 5; $day++) {
            RoomOperatingHour::factory()->create([
                'room_id' => $room->id,
                'day_of_week' => $day,
                'open_time' => '08:00:00',
                'close_time' => '17:00:00',
                'is_closed' => false,
            ]);
        }

        // Saturday 09:00-13:00
        RoomOperatingHour::factory()->create([
            'room_id' => $room->id,
            'day_of_week' => 6,
            'open_time' => '09:00:00',
            'close_time' => '13:00:00',
            'is_closed' => false,
        ]);

        return $room->fresh();
    }

    /**
     * Create a booking with explicit times and status.
     *
     * @param  string  $status  one of: draft, submitted, approved, rejected, cancelled, completed
     */
    protected function createBooking(
        Room $room,
        string $startsAt,
        string $endsAt,
        string $status = 'approved',
    ): Booking {
        $factory = Booking::factory()->state([
            'resource_id' => $room->id,
            'starts_at' => $this->utc($startsAt),
            'ends_at' => $this->utc($endsAt),
        ]);

        // Apply status state
        $factory = match ($status) {
            'draft' => $factory->state(['status' => BookingStatus::Draft]),
            'submitted' => $factory->submitted(),
            'approved' => $factory->approved(),
            'rejected' => $factory->rejected(),
            'cancelled' => $factory->cancelled(),
            'completed' => $factory->completed(),
            default => throw new \InvalidArgumentException("Unknown status: {$status}"),
        };

        return $factory->create();
    }

    /**
     * Create a room block schedule.
     */
    protected function createBlock(
        Room $room,
        string $startsAt,
        string $endsAt,
        bool $cancelled = false,
    ): RoomBlockSchedule {
        $factory = RoomBlockSchedule::factory()->state([
            'room_id' => $room->id,
            'starts_at' => $this->utc($startsAt),
            'ends_at' => $this->utc($endsAt),
            'created_by_user_id' => User::factory(),
        ]);

        if ($cancelled) {
            $factory = $factory->cancelled();
        }

        return $factory->create();
    }
}
