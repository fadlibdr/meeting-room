<?php

namespace Database\Seeders;

use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Models\RoomFacility;
use App\Models\RoomFacilityItem;
use App\Models\RoomOperatingHour;
use Illuminate\Database\Seeder;

class RoomsSeeder extends Seeder
{
    public function run(): void
    {
        $rooms = [
            // 2 rooms with NO approval (small huddle rooms)
            [
                'code' => 'RM-HUDDLE-1',
                'name' => 'Ruang Huddle 1',
                'location' => 'Gedung BPJS Kesehatan Pusat',
                'floor' => 'Lantai 3',
                'capacity' => 4,
                'approval_mode' => RoomApprovalMode::None,
                'booking_buffer_minutes' => 0,
                'description' => 'Ruang diskusi kecil untuk 2-4 orang. Tidak perlu approval.',
                'facilities' => ['WHITEBOARD' => 1, 'WIFI' => 1, 'AC' => 1, 'POWER_OUTLET' => 4],
            ],
            [
                'code' => 'RM-HUDDLE-2',
                'name' => 'Ruang Huddle 2',
                'location' => 'Gedung BPJS Kesehatan Pusat',
                'floor' => 'Lantai 5',
                'capacity' => 4,
                'approval_mode' => RoomApprovalMode::None,
                'booking_buffer_minutes' => 0,
                'description' => 'Ruang diskusi kecil untuk 2-4 orang. Tidak perlu approval.',
                'facilities' => ['WHITEBOARD' => 1, 'WIFI' => 1, 'AC' => 1, 'TV_LCD' => 1],
            ],

            // 4 rooms requiring unit approver
            [
                'code' => 'RM-MEETING-1',
                'name' => 'Ruang Rapat Garuda',
                'location' => 'Gedung BPJS Kesehatan Pusat',
                'floor' => 'Lantai 2',
                'capacity' => 12,
                'approval_mode' => RoomApprovalMode::UnitApprover,
                'booking_buffer_minutes' => 15,
                'description' => 'Ruang rapat menengah dengan video conference.',
                'facilities' => ['PROJECTOR' => 1, 'WHITEBOARD' => 1, 'AC' => 1, 'WIFI' => 1, 'VIDEO_CONF' => 1, 'MIC_WIRELESS' => 2],
            ],
            [
                'code' => 'RM-MEETING-2',
                'name' => 'Ruang Rapat Cendrawasih',
                'location' => 'Gedung BPJS Kesehatan Pusat',
                'floor' => 'Lantai 4',
                'capacity' => 16,
                'approval_mode' => RoomApprovalMode::UnitApprover,
                'booking_buffer_minutes' => 15,
                'description' => 'Ruang rapat untuk diskusi tim besar.',
                'facilities' => ['PROJECTOR' => 1, 'WHITEBOARD' => 1, 'AC' => 1, 'WIFI' => 1, 'TV_LCD' => 1, 'MIC_WIRELESS' => 2],
            ],
            [
                'code' => 'RM-MEETING-3',
                'name' => 'Ruang Rapat Merak',
                'location' => 'Gedung BPJS Kesehatan Pusat',
                'floor' => 'Lantai 6',
                'capacity' => 10,
                'approval_mode' => RoomApprovalMode::UnitApprover,
                'booking_buffer_minutes' => 15,
                'description' => 'Ruang rapat dengan natural lighting.',
                'facilities' => ['PROJECTOR' => 1, 'WHITEBOARD' => 1, 'AC' => 1, 'WIFI' => 1, 'FLIPCHART' => 1],
            ],
            [
                'code' => 'RM-MEETING-4',
                'name' => 'Ruang Rapat Rajawali',
                'location' => 'Gedung BPJS Kesehatan Pusat',
                'floor' => 'Lantai 8',
                'capacity' => 14,
                'approval_mode' => RoomApprovalMode::UnitApprover,
                'booking_buffer_minutes' => 15,
                'description' => 'Ruang rapat dengan view kota.',
                'facilities' => ['PROJECTOR' => 1, 'WHITEBOARD' => 1, 'AC' => 1, 'WIFI' => 1, 'TV_LCD' => 1, 'POWER_OUTLET' => 6],
            ],

            // 2 rooms requiring GA admin approval (high-value spaces)
            [
                'code' => 'RM-AUDITORIUM',
                'name' => 'Auditorium Anggrek',
                'location' => 'Gedung BPJS Kesehatan Pusat',
                'floor' => 'Lantai 10',
                'capacity' => 80,
                'approval_mode' => RoomApprovalMode::GaAdmin,
                'booking_buffer_minutes' => 30,
                'description' => 'Auditorium besar untuk seminar, town hall, dan acara formal.',
                'facilities' => ['PROJECTOR' => 1, 'SOUND_SYSTEM' => 1, 'AC' => 1, 'WIFI' => 1, 'PODIUM' => 1, 'MIC_WIRELESS' => 4, 'VIDEO_CONF' => 1, 'EXTRA_CHAIRS' => 30],
            ],
            [
                'code' => 'RM-BOARD',
                'name' => 'Ruang Direksi',
                'location' => 'Gedung BPJS Kesehatan Pusat',
                'floor' => 'Lantai 12',
                'capacity' => 20,
                'approval_mode' => RoomApprovalMode::GaAdmin,
                'booking_buffer_minutes' => 30,
                'description' => 'Ruang rapat eksekutif. Hanya untuk rapat direksi atau tamu VIP.',
                'facilities' => ['PROJECTOR' => 1, 'TV_LCD' => 2, 'AC' => 1, 'WIFI' => 1, 'VIDEO_CONF' => 1, 'COFFEE_TEA' => 1, 'MIC_WIRELESS' => 4],
            ],
        ];

        foreach ($rooms as $roomData) {
            $facilities = $roomData['facilities'];
            unset($roomData['facilities']);

            $room = Room::firstOrCreate(
                ['code' => $roomData['code']],
                array_merge($roomData, [
                    'status' => RoomStatus::Active,
                    'is_active' => true,
                ])
            );

            $this->attachFacilities($room, $facilities);
            $this->attachOperatingHours($room);
        }
    }

    /**
     * @param  array<string, int>  $facilities  code => quantity
     */
    private function attachFacilities(Room $room, array $facilities): void
    {
        foreach ($facilities as $code => $quantity) {
            $facility = RoomFacility::where('code', $code)->first();
            if (! $facility) {
                continue;
            }

            RoomFacilityItem::firstOrCreate(
                [
                    'room_id' => $room->id,
                    'room_facility_id' => $facility->id,
                ],
                [
                    'quantity' => $quantity,
                    'is_operational' => true,
                ]
            );
        }
    }

    private function attachOperatingHours(Room $room): void
    {
        // Mon-Fri (1-5): 08:00-17:00, weekends closed
        // day_of_week: 0=Sunday, 1=Monday, ..., 6=Saturday (Carbon convention)
        for ($day = 0; $day <= 6; $day++) {
            $isWeekend = ($day === 0 || $day === 6);

            RoomOperatingHour::firstOrCreate(
                [
                    'room_id' => $room->id,
                    'day_of_week' => $day,
                ],
                [
                    'open_time' => $isWeekend ? null : '08:00:00',
                    'close_time' => $isWeekend ? null : '17:00:00',
                    'is_closed' => $isWeekend,
                ]
            );
        }
    }
}
