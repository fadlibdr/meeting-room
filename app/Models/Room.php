<?php

namespace App\Models;

use App\Enums\ResourceType;
use App\Models\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Builder;

/**
 * A meeting room — the default (and legacy) {@see Resource} type.
 *
 * Backed by the same `resources` table; a global scope keeps every query
 * confined to `type = room`, and new rooms are stamped as such on create.
 * This preserves all room-centric behaviour while the wider resource
 * abstraction (Stage 3 E) is rolled out around it.
 */
class Room extends Resource
{
    use HasHashid;

    protected static function booted(): void
    {
        static::addGlobalScope('room', function (Builder $query): void {
            $query->where($query->getModel()->getTable().'.type', ResourceType::Room->value);
        });

        static::creating(function (Room $room): void {
            $room->type ??= ResourceType::Room;
        });
    }
}
