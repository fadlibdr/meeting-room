<?php

namespace Database\Factories;

use App\Enums\ResourceType;
use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Models\Room;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Room>
 */
class RoomFactory extends Factory
{
    protected $model = Room::class;

    public function definition(): array
    {
        return [
            'type' => ResourceType::Room->value,
            'code' => 'RM-'.strtoupper($this->faker->unique()->bothify('??##')),
            'name' => $this->faker->randomElement([
                'Ruang Garuda', 'Ruang Cendrawasih', 'Ruang Merak',
                'Ruang Rajawali', 'Ruang Mawar', 'Ruang Melati',
                'Ruang Anggrek', 'Ruang Kenanga', 'Ruang Bougenville',
            ]).' '.$this->faker->numberBetween(1, 5),
            'location' => 'Gedung BPJS Kesehatan Pusat',
            'floor' => 'Lantai '.$this->faker->numberBetween(1, 12),
            'capacity' => $this->faker->randomElement([6, 8, 10, 12, 16, 20, 30, 50]),
            'status' => RoomStatus::Active,
            'approval_mode' => $this->faker->randomElement([
                RoomApprovalMode::None,
                RoomApprovalMode::UnitApprover,
                RoomApprovalMode::GaAdmin,
            ]),
            'booking_buffer_minutes' => $this->faker->randomElement([0, 15, 30]),
            'description' => $this->faker->sentence(),
            'is_active' => true,
        ];
    }

    public function noApproval(): static
    {
        return $this->state(fn () => ['approval_mode' => RoomApprovalMode::None]);
    }

    public function unitApprover(): static
    {
        return $this->state(fn () => ['approval_mode' => RoomApprovalMode::UnitApprover]);
    }

    public function gaAdmin(): static
    {
        return $this->state(fn () => ['approval_mode' => RoomApprovalMode::GaAdmin]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => RoomStatus::Inactive,
            'is_active' => false,
        ]);
    }
}
