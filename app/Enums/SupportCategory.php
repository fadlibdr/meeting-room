<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Stage 4g.1 — categories for in-app support/contact requests.
 */
enum SupportCategory: string
{
    case Bug = 'bug';
    case Booking = 'booking';
    case Account = 'account';
    case Feature = 'feature';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Bug => 'Laporan Masalah / Bug',
            self::Booking => 'Bantuan Reservasi',
            self::Account => 'Akun & Akses',
            self::Feature => 'Saran Fitur',
            self::Other => 'Lainnya',
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
