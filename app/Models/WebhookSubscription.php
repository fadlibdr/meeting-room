<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\WebhookSubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A subscribed webhook endpoint (Stage 3 C2).
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property array<int, string> $events
 * @property bool $is_active
 * @property int|null $created_by_user_id
 */
class WebhookSubscription extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<WebhookSubscriptionFactory> */
    use HasFactory;

    protected $fillable = ['name', 'url', 'secret', 'events', 'is_active', 'created_by_user_id'];

    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['events' => 'array', 'is_active' => 'boolean'];
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * Active subscriptions whose event set includes $event.
     *
     * @param  Builder<WebhookSubscription>  $query
     * @return Builder<WebhookSubscription>
     */
    public function scopeListeningFor(Builder $query, string $event): Builder
    {
        return $query->where('is_active', true)
            ->whereJsonContains('events', $event);
    }
}
