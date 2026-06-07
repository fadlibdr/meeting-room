<?php

namespace Database\Factories;

use App\Models\CalendarConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CalendarConnection>
 */
class CalendarConnectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'microsoft',
            'access_token' => 'tok-'.Str::random(20),
            'refresh_token' => 'ref-'.Str::random(20),
            'token_expires_at' => now()->addHour(),
            'external_calendar_id' => null,
            'is_active' => true,
        ];
    }

    public function google(): static
    {
        return $this->state(fn () => ['provider' => 'google']);
    }
}
