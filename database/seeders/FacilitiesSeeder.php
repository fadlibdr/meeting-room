<?php

namespace Database\Seeders;

use App\Models\RoomFacility;
use Illuminate\Database\Seeder;

class FacilitiesSeeder extends Seeder
{
    public function run(): void
    {
        $facilities = [
            // AV
            ['code' => 'PROJECTOR', 'name' => 'Proyektor', 'category' => 'av', 'icon' => 'projector'],
            ['code' => 'TV_LCD', 'name' => 'TV LCD', 'category' => 'av', 'icon' => 'tv'],
            ['code' => 'MIC_WIRELESS', 'name' => 'Mikrofon Wireless', 'category' => 'av', 'icon' => 'mic'],
            ['code' => 'MIC_WIRED', 'name' => 'Mikrofon Kabel', 'category' => 'av', 'icon' => 'mic'],
            ['code' => 'SOUND_SYSTEM', 'name' => 'Sound System', 'category' => 'av', 'icon' => 'speaker'],
            ['code' => 'VIDEO_CONF', 'name' => 'Video Conference', 'category' => 'av', 'icon' => 'video'],

            // Writing
            ['code' => 'WHITEBOARD', 'name' => 'Whiteboard', 'category' => 'writing', 'icon' => 'pencil'],
            ['code' => 'FLIPCHART', 'name' => 'Flipchart', 'category' => 'writing', 'icon' => 'flipchart'],

            // Connectivity
            ['code' => 'WIFI', 'name' => 'WiFi', 'category' => 'connectivity', 'icon' => 'wifi'],
            ['code' => 'LAN', 'name' => 'LAN Cable', 'category' => 'connectivity', 'icon' => 'network'],
            ['code' => 'POWER_OUTLET', 'name' => 'Stop Kontak Tambahan', 'category' => 'connectivity', 'icon' => 'plug'],

            // Comfort
            ['code' => 'AC', 'name' => 'AC', 'category' => 'comfort', 'icon' => 'wind'],
            ['code' => 'COFFEE_TEA', 'name' => 'Coffee & Tea Setup', 'category' => 'comfort', 'icon' => 'coffee'],

            // Furniture
            ['code' => 'PODIUM', 'name' => 'Podium', 'category' => 'furniture', 'icon' => 'podium'],
            ['code' => 'EXTRA_CHAIRS', 'name' => 'Kursi Tambahan', 'category' => 'furniture', 'icon' => 'chair'],
        ];

        foreach ($facilities as $f) {
            RoomFacility::firstOrCreate(
                ['code' => $f['code']],
                array_merge($f, ['is_active' => true])
            );
        }
    }
}
