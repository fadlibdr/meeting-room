<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ApprovalDelegation;

/**
 * Re-routes an approver to their active delegate (Stage 3 B).
 *
 * One hop only — a delegation chain (A→B→C) routes A to B, not C — to keep
 * resolution terminating and predictable. Shared by the multi-step chain
 * resolver and the legacy single-step routing.
 */
final class DelegationResolver
{
    public function resolve(int $userId): int
    {
        $delegateId = ApprovalDelegation::query()
            ->activeAt()
            ->where('from_user_id', $userId)
            ->orderByDesc('starts_at')
            ->value('to_user_id');

        return $delegateId !== null ? (int) $delegateId : $userId;
    }
}
