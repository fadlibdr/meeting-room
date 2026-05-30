<?php

namespace Database\Factories;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ActivityLog>
 */
class ActivityLogFactory extends Factory
{
    protected $model = ActivityLog::class;

    public function definition(): array
    {
        return [
            'actor_user_id' => User::factory(),
            'module' => $this->faker->randomElement(['bookings', 'rooms', 'users', 'auth']),
            'event' => $this->faker->randomElement(['created', 'updated', 'submitted', 'approved', 'login']),
            'subject_type' => null,
            'subject_id' => null,
            'description' => $this->faker->sentence(),
            'old_values' => null,
            'new_values' => null,
            'context' => null,
            'ip_address' => $this->faker->ipv4(),
            'user_agent' => $this->faker->userAgent(),
            'created_at' => now(),
        ];
    }
}
