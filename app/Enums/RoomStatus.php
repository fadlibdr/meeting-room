<?php

namespace App\Enums;

enum RoomStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Inactive => 'Nonaktif',
            self::Archived => 'Arsip',
        };
    }

    public function isBookable(): bool
    {
        return $this === self::Active;
    }
}
