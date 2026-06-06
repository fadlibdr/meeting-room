<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The kind of bookable resource (Stage 3 E — resource generalization).
 *
 * `Room` is the legacy/default type; every pre-existing row is a room. The
 * other types let the same booking + conflict machinery schedule non-room
 * resources (equipment, vehicles, hot desks) once E2 generalizes the UI.
 */
enum ResourceType: string
{
    case Room = 'room';
    case Equipment = 'equipment';
    case Vehicle = 'vehicle';
    case Desk = 'desk';

    public function label(): string
    {
        return match ($this) {
            self::Room => 'Ruangan',
            self::Equipment => 'Peralatan',
            self::Vehicle => 'Kendaraan',
            self::Desk => 'Meja Kerja',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $c) => $c->value, self::cases());
    }
}
