<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use DomainException;

/**
 * Thrown when SubmitBookingAction cannot resolve a valid approver
 * for a booking that requires approval.
 *
 * Two failure modes:
 *  - unit_approver mode: requester has no approver_user_id set
 *  - ga_admin mode: no active GA admin exists in the system
 *
 * Both indicate misconfigured master data — admin must intervene.
 * The exception message is in Indonesian for end-user UX.
 */
class ApprovalRoutingException extends DomainException
{
    public static function noUnitApprover(User $requester): self
    {
        return new self(
            "Pemesan {$requester->email} (ID {$requester->id}) belum memiliki approver yang ditugaskan. "
            .'Silakan hubungi GA Admin untuk menugaskan approver sebelum membuat reservasi.'
        );
    }

    public static function noGaAdmin(): self
    {
        return new self(
            'Tidak ada GA Admin aktif dalam sistem. '
            .'Reservasi untuk ruang dengan persetujuan GA Admin tidak dapat diproses. '
            .'Silakan hubungi Super Admin.'
        );
    }

    public static function unresolvableStep(int $policyId, int $sequenceNo): self
    {
        return new self(
            "Kebijakan persetujuan #{$policyId} langkah {$sequenceNo} tidak dapat menentukan approver "
            .'(approver atau peran tidak valid / tidak ada pengguna aktif). Silakan perbaiki konfigurasi kebijakan.'
        );
    }
}
