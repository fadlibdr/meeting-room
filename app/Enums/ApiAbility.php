<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sanctum token abilities (scopes) for the public API (Stage 3 C).
 *
 * `read` grants the read endpoints; `booking:write` additionally permits
 * creating bookings — which still routes through SubmitBookingAction and the
 * user's own permissions/approval rules, so a token never exceeds its owner.
 */
enum ApiAbility: string
{
    case Read = 'read';
    case BookingWrite = 'booking:write';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Baca (rooms, ketersediaan, reservasi saya)',
            self::BookingWrite => 'Buat reservasi',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $a): string => $a->value, self::cases());
    }
}
