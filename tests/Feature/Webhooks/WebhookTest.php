<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Actions\ApproveBookingAction;
use App\Enums\WebhookEvent;
use App\Jobs\SendWebhookJob;
use App\Models\Booking;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookSubscription;
use App\Services\WebhookDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class WebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatch_queues_only_matching_active_subscriptions(): void
    {
        Queue::fake();
        $booking = Booking::factory()->approved()->create();

        WebhookSubscription::factory()->create(['events' => ['booking.approved'], 'is_active' => true]);
        WebhookSubscription::factory()->create(['events' => ['booking.cancelled'], 'is_active' => true]); // wrong event
        WebhookSubscription::factory()->create(['events' => ['booking.approved'], 'is_active' => false]); // inactive

        app(WebhookDispatcher::class)->dispatch(WebhookEvent::BookingApproved, $booking);

        Queue::assertPushed(SendWebhookJob::class, 1);
        $this->assertDatabaseCount('webhook_deliveries', 1);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'booking.approved', 'status' => 'pending']);
    }

    public function test_job_signs_the_payload_and_marks_success(): void
    {
        Http::fake(['*' => Http::response('', 200)]);
        $subscription = WebhookSubscription::factory()->create(['secret' => 's3cr3t', 'url' => 'https://203.0.113.10/in']);
        $delivery = WebhookDelivery::factory()->create([
            'webhook_subscription_id' => $subscription->id,
            'event' => 'booking.approved',
            'payload' => ['event' => 'booking.approved', 'booking' => ['id' => 1]],
        ]);

        (new SendWebhookJob($delivery))->handle();

        Http::assertSent(function ($request) {
            $expected = 'sha256='.hash_hmac('sha256', $request->body(), 's3cr3t');

            return $request->url() === 'https://203.0.113.10/in'
                && $request->hasHeader('X-Webhook-Event', 'booking.approved')
                && hash_equals($expected, $request->header('X-Webhook-Signature')[0]);
        });

        $delivery->refresh();
        $this->assertSame('success', $delivery->status);
        $this->assertSame(200, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
    }

    public function test_job_records_failure_and_retries(): void
    {
        Http::fake(['*' => Http::response('boom', 500)]);
        $delivery = WebhookDelivery::factory()->create();

        try {
            (new SendWebhookJob($delivery))->handle();
            $this->fail('Expected the job to throw for retry.');
        } catch (RuntimeException) {
            // expected — the queue would retry.
        }

        $delivery->refresh();
        $this->assertSame(500, $delivery->response_status);
        $this->assertSame(1, $delivery->attempts);
        $this->assertSame('pending', $delivery->status); // still retrying

        (new SendWebhookJob($delivery))->failed(new RuntimeException('gave up'));
        $this->assertSame('failed', $delivery->refresh()->status);
    }

    public function test_approving_a_booking_emits_a_webhook(): void
    {
        Queue::fake();
        WebhookSubscription::factory()->create(['events' => ['booking.approved'], 'is_active' => true]);

        // A submitted booking with a pending approval row at step 1.
        $approver = User::factory()->create(['is_active' => true]);
        $booking = Booking::factory()->submitted()->create([
            'current_approval_step' => 1,
            'current_approver_user_id' => $approver->id,
        ]);
        $booking->approvals()->create(['sequence_no' => 1, 'approver_user_id' => $approver->id, 'status' => 'pending']);

        app(ApproveBookingAction::class)->execute($booking, $approver);

        Queue::assertPushed(SendWebhookJob::class, 1);
        $this->assertDatabaseHas('webhook_deliveries', ['event' => 'booking.approved']);
    }
}
