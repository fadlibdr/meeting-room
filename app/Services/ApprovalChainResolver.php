<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ApprovalStepType;
use App\Exceptions\ApprovalRoutingException;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\User;

/**
 * Expands an approval policy into an ordered chain of concrete approver user ids
 * for a given requester (Stage 3 B).
 *
 * Each step resolves to one approver (unit_approver / role / user); the result
 * is passed through delegation (an away approver routes to their stand-in) and
 * then de-duplicated — the requester is never their own approver, and no one is
 * asked twice — preserving step order.
 */
final class ApprovalChainResolver
{
    public function __construct(
        private readonly DelegationResolver $delegations,
    ) {}

    /**
     * @return list<int> ordered, distinct approver user ids
     *
     * @throws ApprovalRoutingException when a step resolves to no approver
     */
    public function resolve(ApprovalPolicy $policy, User $requester): array
    {
        /** @var list<int> $chain */
        $chain = [];

        foreach ($policy->steps as $step) {
            $userId = $this->resolveStep($step, $requester);

            if ($userId === null) {
                throw ApprovalRoutingException::unresolvableStep($policy->id, $step->sequence_no);
            }

            $userId = $this->delegations->resolve($userId);

            // No self-approval, no double-asking the same approver.
            if ($userId === $requester->id || in_array($userId, $chain, true)) {
                continue;
            }

            $chain[] = $userId;
        }

        return $chain;
    }

    private function resolveStep(ApprovalPolicyStep $step, User $requester): ?int
    {
        return match ($step->approver_type) {
            ApprovalStepType::UnitApprover => $requester->approver_user_id,
            ApprovalStepType::User => $step->approver_user_id,
            ApprovalStepType::Role => $this->firstActiveUserWithRole($step->role_id),
        };
    }

    private function firstActiveUserWithRole(?int $roleId): ?int
    {
        if ($roleId === null) {
            return null;
        }

        $id = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('roles.id', $roleId))
            ->orderBy('id')
            ->value('id');

        return $id !== null ? (int) $id : null;
    }
}
