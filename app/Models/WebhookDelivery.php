<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One webhook delivery attempt-log (Stage 3 C2). Updated across the queued
 * job's retries.
 *
 * @property int $id
 * @property int $webhook_subscription_id
 * @property string $event
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int|null $response_status
 * @property int $attempts
 * @property Carbon|null $last_attempt_at
 * @property string|null $error
 */
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    protected $fillable = [
        'webhook_subscription_id', 'event', 'payload', 'status',
        'response_status', 'attempts', 'last_attempt_at', 'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'last_attempt_at' => 'datetime',
            'attempts' => 'integer',
            'response_status' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<WebhookSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
