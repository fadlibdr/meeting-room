<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Support\PublicUrlGuard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Stage 3 C2 — delivers one webhook with an HMAC-SHA256 signature, retrying on
 * failure. The exact JSON body is signed (X-Webhook-Signature) so the receiver
 * can verify integrity with the shared secret. The delivery row is updated each
 * attempt; failed() marks it failed after the final retry.
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        private readonly WebhookDelivery $delivery,
    ) {}

    public function handle(): void
    {
        $this->deliver();
    }

    private function deliver(): void
    {
        $delivery = $this->delivery->fresh('subscription');
        if ($delivery === null || $delivery->subscription === null) {
            return;
        }

        $subscription = $delivery->subscription;

        // Re-validate the target at send time: a URL that was public when saved
        // can be re-pointed to an internal address (DNS rebinding). Reject SSRF
        // targets here, not just at creation.
        if (! PublicUrlGuard::isPublicUrl($subscription->url)) {
            $delivery->forceFill([
                'status' => 'failed',
                'error' => 'Blocked: webhook target resolves to a non-public address.',
            ])->save();

            return;
        }

        $body = (string) json_encode($delivery->payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha256', $body, $subscription->secret);

        $delivery->forceFill([
            'attempts' => $delivery->attempts + 1,
            'last_attempt_at' => now(),
        ])->save();

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
            'X-Webhook-Event' => $delivery->event,
            'X-Webhook-Signature' => 'sha256='.$signature,
            'X-Webhook-Delivery' => (string) $delivery->id,
        ])->timeout(10)->withoutRedirecting()->withBody($body, 'application/json')->post($subscription->url);

        $delivery->forceFill(['response_status' => $response->status()])->save();

        if ($response->successful()) {
            $delivery->forceFill(['status' => 'success', 'error' => null])->save();

            return;
        }

        // Non-2xx → throw so the queue retries (status stays pending until failed()).
        throw new RuntimeException("Webhook delivery {$delivery->id} failed with HTTP {$response->status()}.");
    }

    public function failed(Throwable $exception): void
    {
        $this->delivery->fresh()?->forceFill([
            'status' => 'failed',
            'error' => mb_substr($exception->getMessage(), 0, 480),
        ])->save();
    }
}
