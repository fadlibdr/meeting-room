<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's override for a (notification type, channel). Only consulted when the
 * matching admin default is user_overridable. Tenant-scoped.
 *
 * @property int $user_id
 * @property string $type
 * @property string $channel
 * @property bool $enabled
 */
class NotificationPreference extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'user_id', 'type', 'channel', 'enabled'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
