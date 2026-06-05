<?php

namespace Database\Factories;

use App\Enums\RoomBlockType;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomBlockSchedule>
 */
class RoomBlockScheduleFactory extends Factory
{
    protected $model = RoomBlockSchedule::class;

    public function definition(): array
    {
        $start = $this->faker->dateTimeBetween('+1 day', '+30 days');
        $end = (clone $start)->modify('+'.$this->faker->numberBetween(2, 8).' hours');

        return [
            'room_id' => Room::factory(),
            'recurrence_group_id' => null,
            'block_type' => $this->faker->randomElement(RoomBlockType::cases()),
            'title' => $this->faker->randomElement([
                'Pemeliharaan AC',
                'Pembersihan Mendalam',
                'Acara Internal',
                'Penggantian Proyektor',
            ]),
            'reason' => $this->faker->sentence(),
            'starts_at' => $start,
            'ends_at' => $end,
            'created_by_user_id' => User::factory(),
            'cancelled_at' => null,
            'cancelled_by_user_id' => null,
            'is_active' => true,
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'cancelled_at' => now(),
            'cancelled_by_user_id' => User::factory(),
            'is_active' => false,
        ]);
    }
}
