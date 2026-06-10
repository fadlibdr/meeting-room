<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Seating/layout arrangement requested for a booking.
 */
enum RoomLayout: string
{
    case RoundTable = 'round_table';
    case UShape = 'u_shape';
    case Theater = 'theater';
    case Classroom = 'classroom';
    case Boardroom = 'boardroom';
    case Standing = 'standing';

    public function label(): string
    {
        return match ($this) {
            self::RoundTable => 'Meja Bundar',
            self::UShape => 'Bentuk U',
            self::Theater => 'Teater',
            self::Classroom => 'Kelas',
            self::Boardroom => 'Rapat / Boardroom',
            self::Standing => 'Berdiri / Resepsi',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }
}
