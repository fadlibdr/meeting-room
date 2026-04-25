<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $code = $this->faker->unique()->slug(2);

        return [
            'code' => $code,
            'name' => ucwords(str_replace('-', ' ', $code)),
            'description' => $this->faker->sentence(),
            'scope' => $this->faker->randomElement(['operational', 'admin', 'system']),
            'is_system' => false,
            'is_active' => true,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true, 'scope' => 'system']);
    }
}
