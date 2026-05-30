<?php

namespace Database\Factories;

use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'unit_id' => Unit::factory(),
            'employee_no' => 'EMP'.$this->faker->unique()->numerify('######'),
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'job_title' => $this->faker->jobTitle(),
            'approver_user_id' => null,
            'is_active' => true,
            'timezone' => 'Asia/Jakarta',
            'last_login_at' => null,
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'failed_login_attempts' => 5,
            'locked_until' => now()->addMinutes(30),
        ]);
    }
}
