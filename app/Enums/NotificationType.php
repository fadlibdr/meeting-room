<?php

namespace App\Enums;

enum NotificationType: string
{
    case BookingSubmitted = 'booking_submitted';
    case BookingApproved = 'booking_approved';
    case BookingRejected = 'booking_rejected';
    case BookingCancelled = 'booking_cancelled';
    case BookingReminder = 'booking_reminder';
    case RoomBlockCreated = 'room_block_created';
    case ExportReady = 'export_ready';
    case BookingAutoReleased = 'booking_auto_released';

    public function label(): string
    {
        return match ($this) {
            self::BookingSubmitted => 'Reservasi Diajukan',
            self::BookingApproved => 'Reservasi Disetujui',
            self::BookingRejected => 'Reservasi Ditolak',
            self::BookingCancelled => 'Reservasi Dibatalkan',
            self::BookingReminder => 'Pengingat Reservasi',
            self::RoomBlockCreated => 'Pemblokiran Ruang',
            self::ExportReady => 'Ekspor Siap',
            self::BookingAutoReleased => 'Reservasi Dilepas Otomatis',
        };
    }

    /**
     * The notification types whose channels are configurable per (type, channel)
     * by admins, with optional per-user overrides.
     *
     * @return array<int, self>
     */
    public static function configurableCases(): array
    {
        return [
            self::BookingSubmitted,
            self::BookingApproved,
            self::BookingRejected,
            self::BookingCancelled,
            self::BookingReminder,
            self::BookingAutoReleased,
        ];
    }
}
