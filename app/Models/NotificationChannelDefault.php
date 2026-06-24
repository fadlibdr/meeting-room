<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin default for a (notification type, channel): whether it is enabled by
 * default and whether users may override it. Tenant-scoped.
 *
 * @property string $type
 * @property string $channel
 * @property bool $enabled
 * @property bool $user_overridable
 */
class NotificationChannelDefault extends Model
{
    protected $fillable = ['tenant_id', 'type', 'channel', 'enabled', 'user_overridable'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'user_overridable' => 'boolean',
        ];
    }
}
