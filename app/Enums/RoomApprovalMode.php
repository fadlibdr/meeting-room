<?php

namespace App\Enums;

enum RoomApprovalMode: string
{
    case None = 'none';
    case UnitApprover = 'unit_approver';
    case GaAdmin = 'ga_admin';

    public function label(): string
    {
        return match ($this) {
            self::None => 'Tidak perlu approval',
            self::UnitApprover => 'Approval unit',
            self::GaAdmin => 'Approval GA Admin',
        };
    }

    public function requiresApproval(): bool
    {
        return $this !== self::None;
    }
}
