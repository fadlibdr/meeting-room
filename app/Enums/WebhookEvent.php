<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Booking lifecycle events a webhook can subscribe to (Stage 3 C2).
 */
enum WebhookEvent: string
{
    case BookingSubmitted = 'booking.submitted';
    case BookingApproved = 'booking.approved';
    case BookingRejected = 'booking.rejected';
    case BookingCancelled = 'booking.cancelled';
    case BookingAutoReleased = 'booking.auto_released';
    case BookingCheckedIn = 'booking.checked_in';

    public function label(): string
    {
        return match ($this) {
            self::BookingSubmitted => 'Reservasi diajukan',
            self::BookingApproved => 'Reservasi disetujui',
            self::BookingRejected => 'Reservasi ditolak',
            self::BookingCancelled => 'Reservasi dibatalkan',
            self::BookingAutoReleased => 'Reservasi dilepas otomatis (no-show)',
            self::BookingCheckedIn => 'Check-in',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $e): string => $e->value, self::cases());
    }
}
