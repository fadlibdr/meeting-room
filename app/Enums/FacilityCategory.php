<?php

namespace App\Enums;

enum FacilityCategory: string
{
    case Av = 'av';
    case Furniture = 'furniture';
    case Connectivity = 'connectivity';
    case Comfort = 'comfort';

    public function label(): string
    {
        return match ($this) {
            self::Av => 'Audio Visual',
            self::Furniture => 'Furnitur',
            self::Connectivity => 'Konektivitas',
            self::Comfort => 'Kenyamanan',
        };
    }

    /**
     * Backing values — the single source of truth for the facility category
     * allow-list (Rule::in validation, select options, and the factory).
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
