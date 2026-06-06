<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * How an approval-policy step resolves to a concrete approver (Stage 3 B).
 */
enum ApprovalStepType: string
{
    /** The requester's own unit approver (User::approver_user_id). */
    case UnitApprover = 'unit_approver';

    /** Any active holder of the step's role. */
    case Role = 'role';

    /** A specific named user. */
    case User = 'user';

    public function label(): string
    {
        return match ($this) {
            self::UnitApprover => 'Approver unit pemohon',
            self::Role => 'Pemegang peran',
            self::User => 'Pengguna tertentu',
        };
    }
}
