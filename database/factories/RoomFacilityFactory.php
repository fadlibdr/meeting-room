<?php

namespace Database\Factories;

use App\Enums\FacilityCategory;
use App\Models\RoomFacility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomFacility>
 */
class RoomFacilityFactory extends Factory
{
    protected $model = RoomFacility::class;

    public function definition(): array
    {
        $facilities = [
            ['code' => 'PROJECTOR', 'name' => 'Proyektor', 'category' => FacilityCategory::Av->value, 'icon' => 'projector'],
            ['code' => 'WHITEBOARD', 'name' => 'Whiteboard', 'category' => FacilityCategory::Furniture->value, 'icon' => 'pencil'],
            ['code' => 'AC', 'name' => 'AC', 'category' => FacilityCategory::Comfort->value, 'icon' => 'wind'],
            ['code' => 'TV', 'name' => 'TV LCD', 'category' => FacilityCategory::Av->value, 'icon' => 'tv'],
            ['code' => 'WIFI', 'name' => 'WiFi', 'category' => FacilityCategory::Connectivity->value, 'icon' => 'wifi'],
            ['code' => 'MICROPHONE', 'name' => 'Mikrofon', 'category' => FacilityCategory::Av->value, 'icon' => 'mic'],
            ['code' => 'SOUND_SYSTEM', 'name' => 'Sound System', 'category' => FacilityCategory::Av->value, 'icon' => 'speaker'],
            ['code' => 'VIDEO_CONF', 'name' => 'Video Conference', 'category' => FacilityCategory::Av->value, 'icon' => 'video'],
        ];

        $f = $this->faker->randomElement($facilities);

        return [
            'code' => $f['code'].'-'.$this->faker->unique()->randomNumber(4),
            'name' => $f['name'],
            'category' => $f['category'],
            'icon' => $f['icon'],
            'is_active' => true,
        ];
    }
}
