<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomOperatingHour;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomOperatingHour>
 */
class RoomOperatingHourFactory extends Factory
{
    protected $model = RoomOperatingHour::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'day_of_week' => $this->faker->numberBetween(0, 6),
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
            'is_closed' => false,
        ];
    }

    public function weekendClosed(): static
    {
        return $this->state(fn () => [
            'is_closed' => true,
            'open_time' => null,
            'close_time' => null,
        ]);
    }
}
