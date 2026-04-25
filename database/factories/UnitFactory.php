<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement([
            'Direktorat Kepesertaan',
            'Direktorat Pelayanan Peserta',
            'Direktorat Keuangan dan Investasi',
            'Direktorat SDM dan Umum',
            'Direktorat Hukum dan Kepatuhan',
            'Direktorat Teknologi Informasi',
            'Biro Komunikasi',
            'Biro Hubungan Antar Lembaga',
            'Kantor Cabang Jakarta Pusat',
            'Kantor Cabang Bandung',
        ]);

        return [
            'code' => 'UNIT-'.strtoupper($this->faker->unique()->bothify('???##')),
            'name' => $name,
            'parent_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
