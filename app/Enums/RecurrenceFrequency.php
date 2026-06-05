<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Harian',
            self::Weekly => 'Mingguan',
            self::Monthly => 'Bulanan',
        };
    }

    /** Indonesian unit label for "every N <unit>". */
    public function unitLabel(): string
    {
        return match ($this) {
            self::Daily => 'hari',
            self::Weekly => 'minggu',
            self::Monthly => 'bulan',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $f): string => $f->value, self::cases());
    }
}
