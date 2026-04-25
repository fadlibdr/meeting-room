<?php

namespace App\Enums;

enum RoomBlockType: string
{
    case Maintenance = 'maintenance';
    case InternalEvent = 'internal_event';
    case Cleaning = 'cleaning';
    case Reserved = 'reserved';

    public function label(): string
    {
        return match ($this) {
            self::Maintenance => 'Pemeliharaan',
            self::InternalEvent => 'Acara Internal',
            self::Cleaning => 'Pembersihan',
            self::Reserved => 'Dicadangkan',
        };
    }
}
