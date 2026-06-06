<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\WebhookEvent;
use App\Jobs\SendWebhookJob;
use App\Models\Booking;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;

/**
 * Stage 3 C2 — fans a booking lifecycle event out to subscribed webhooks.
 *
 * Called from each action's POST-COMMIT section (alongside the notifications),
 * so a rolled-back transaction never emits a webhook. Creates one delivery log
 * per subscription and queues a signed SendWebhookJob (afterCommit for safety).
 */
final class WebhookDispatcher
{
    public function dispatch(WebhookEvent $event, Booking $booking): void
    {
        $subscriptions = WebhookSubscription::query()->listeningFor($event->value)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = $this->payload($event, $booking);

        foreach ($subscriptions as $subscription) {
            $delivery = WebhookDelivery::create([
                'webhook_subscription_id' => $subscription->id,
                'event' => $event->value,
                'payload' => $payload,
                'status' => 'pending',
            ]);

            SendWebhookJob::dispatch($delivery)->afterCommit();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(WebhookEvent $event, Booking $booking): array
    {
        return [
            'event' => $event->value,
            'occurred_at' => now()->toIso8601String(),
            'booking' => [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'subject' => $booking->subject,
                'status' => $booking->status->value,
                'room_id' => $booking->room_id,
                'requester_user_id' => $booking->requester_user_id,
                'starts_at' => $booking->starts_at->toIso8601String(),
                'ends_at' => $booking->ends_at->toIso8601String(),
            ],
        ];
    }
}
