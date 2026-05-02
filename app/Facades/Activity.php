<?php

declare(strict_types=1);

namespace App\Facades;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Facade;

/**
 * @method static ActivityLog log(string $module, string $event, ?Model $subject = null, array<string, mixed> $payload = [], ?User $actor = null)
 * @method static ActivityLog logUserEvent(string $event, User $subject, array<string, mixed> $payload = [])
 *
 * @see ActivityLogger
 */
class Activity extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ActivityLogger::class;
    }
}
