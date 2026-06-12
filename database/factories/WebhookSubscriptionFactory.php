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
            // RFC 5737 TEST-NET (public, routable-looking) so the SSRF guard
            // treats it as a deliverable target in tests; Http::fake intercepts.
            'url' => 'https://203.0.113.10/webhooks/'.$this->faker->uuid(),
            'secret' => Str::random(40),
            'events' => ['booking.approved'],
            'is_active' => true,
            'created_by_user_id' => null,
        ];
    }
}
