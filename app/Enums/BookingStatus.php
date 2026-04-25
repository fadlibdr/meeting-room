<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Menunggu Approval',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Cancelled => 'Dibatalkan',
            self::Completed => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'amber',
            self::Approved => 'green',
            self::Rejected => 'red',
            self::Cancelled => 'gray',
            self::Completed => 'blue',
        };
    }

    /**
     * Status yang mengunci slot (conflict check).
     * Per Blueprint §H.3.
     */
    public function locksSlot(): bool
    {
        return in_array($this, [self::Submitted, self::Approved], true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Rejected, self::Cancelled, self::Completed], true);
    }
}
