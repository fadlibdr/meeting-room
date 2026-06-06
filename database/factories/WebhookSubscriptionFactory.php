<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WebhookSubscription>
 */
class WebhookSubscriptionFactory extends Factory
{
    protected $model = WebhookSubscription::class;

    public function definition(): array
    {
        return [
            'name' => 'Hook '.$this->faker->unique()->bothify('??-###'),
            'url' => 'https://example.test/webhooks/'.$this->faker->uuid(),
            'secret' => Str::random(40),
            'events' => ['booking.approved'],
            'is_active' => true,
            'created_by_user_id' => null,
        ];
    }
}
