<?php

namespace Database\Factories;

use App\Enums\ResourceType;
use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Models\Resource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    protected $model = Resource::class;

    public function definition(): array
    {
        return [
            'type' => ResourceType::Equipment->value,
            'code' => 'RES-'.strtoupper($this->faker->unique()->bothify('??##')),
            'name' => $this->faker->randomElement([
                'Proyektor Epson', 'Mobil Dinas Avanza', 'Speaker Portabel',
                'Laptop Presentasi', 'Meja Hot Desk', 'Kamera Konferensi',
            ]).' '.$this->faker->numberBetween(1, 5),
            'location' => 'Gedung BPJS Kesehatan Pusat',
            'floor' => 'Lantai '.$this->faker->numberBetween(1, 12),
            'capacity' => $this->faker->randomElement([1, 1, 1, 4]),
            'status' => RoomStatus::Active,
            'approval_mode' => RoomApprovalMode::None,
            'booking_buffer_minutes' => 0,
            'description' => $this->faker->sentence(),
            'metadata' => null,
            'is_active' => true,
        ];
    }

    public function ofType(ResourceType $type): static
    {
        return $this->state(fn () => ['type' => $type->value]);
    }
}
