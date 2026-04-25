<?php

namespace Database\Factories;

use App\Models\Room;
use App\Models\RoomFacility;
use App\Models\RoomFacilityItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomFacilityItem>
 */
class RoomFacilityItemFactory extends Factory
{
    protected $model = RoomFacilityItem::class;

    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'room_facility_id' => RoomFacility::factory(),
            'quantity' => $this->faker->numberBetween(1, 4),
            'is_operational' => true,
            'notes' => null,
        ];
    }
}
