<?php

namespace Database\Factories;

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
            ['code' => 'PROJECTOR', 'name' => 'Proyektor', 'category' => 'av', 'icon' => 'projector'],
            ['code' => 'WHITEBOARD', 'name' => 'Whiteboard', 'category' => 'writing', 'icon' => 'pencil'],
            ['code' => 'AC', 'name' => 'AC', 'category' => 'comfort', 'icon' => 'wind'],
            ['code' => 'TV', 'name' => 'TV LCD', 'category' => 'av', 'icon' => 'tv'],
            ['code' => 'WIFI', 'name' => 'WiFi', 'category' => 'connectivity', 'icon' => 'wifi'],
            ['code' => 'MICROPHONE', 'name' => 'Mikrofon', 'category' => 'av', 'icon' => 'mic'],
            ['code' => 'SOUND_SYSTEM', 'name' => 'Sound System', 'category' => 'av', 'icon' => 'speaker'],
            ['code' => 'VIDEO_CONF', 'name' => 'Video Conference', 'category' => 'av', 'icon' => 'video'],
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
