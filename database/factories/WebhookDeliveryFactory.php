<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    protected $model = WebhookDelivery::class;

    public function definition(): array
    {
        return [
            'webhook_subscription_id' => WebhookSubscription::factory(),
            'event' => 'booking.approved',
            'payload' => ['event' => 'booking.approved'],
            'status' => 'pending',
            'attempts' => 0,
        ];
    }
}
