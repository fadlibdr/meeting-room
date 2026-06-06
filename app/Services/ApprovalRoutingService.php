<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\SubmitBookingAction;
use App\Actions\SubmitDraftAction;
use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Exceptions\ApprovalRoutingException;
use App\Models\ApprovalPolicy;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Resolves a booking's initial approval state — now as a CHAIN (Stage 3 B).
 *
 * Resolution order:
 *   1. Room's approval policy, else the requester-unit's approval policy
 *      (configurable multi-step chains) — when present and active.
 *   2. Otherwise the legacy per-room approval_mode (None / UnitApprover /
 *      GaAdmin), which yields a 0- or 1-step chain — fully backward compatible.
 *
 * Returns the status the booking should take plus the ordered list of approver
 * user ids (already de-duplicated and delegation-routed). An empty chain means
 * no approval is required → Approved. Pure routing: no DB writes, no locks —
 * the calling action owns the transaction.
 *
 * @see SubmitBookingAction
 * @see SubmitDraftAction
 * @see ApprovalChainResolver
 */
final class ApprovalRoutingService
{
    public function __construct(
        private readonly ApprovalChainResolver $chainResolver,
        private readonly DelegationResolver $delegations,
    ) {}

    /**
     * @return array{status: BookingStatus, chain: list<int>, approved_at: ?Carbon}
     *
     * @throws ApprovalRoutingException when an approver cannot be resolved
     */
    public function resolve(User $requester, Room $room): array
    {
        $policy = $this->resolvePolicy($room, $requester);

        if ($policy !== null) {
            $chain = $this->chainResolver->resolve($policy, $requester);

            return $chain === []
                ? $this->autoApproved()
                : ['status' => BookingStatus::Submitted, 'chain' => $chain, 'approved_at' => null];
        }

        return match ($room->approval_mode) {
            RoomApprovalMode::None => $this->autoApproved(),
            RoomApprovalMode::UnitApprover => $this->legacyUnitApprover($requester),
            RoomApprovalMode::GaAdmin => $this->legacyGaAdmin(),
        };
    }

    private function resolvePolicy(Room $room, User $requester): ?ApprovalPolicy
    {
        $policy = $room->approvalPolicy;

        if ($policy === null) {
            $unit = $requester->unit;
            $policy = $unit instanceof Unit ? $unit->approvalPolicy : null;
        }

        return ($policy !== null && $policy->is_active) ? $policy : null;
    }

    /**
     * @return array{status: BookingStatus, chain: list<int>, approved_at: ?Carbon}
     */
    private function autoApproved(): array
    {
        return ['status' => BookingStatus::Approved, 'chain' => [], 'approved_at' => Carbon::now()];
    }

    /**
     * @return array{status: BookingStatus, chain: list<int>, approved_at: ?Carbon}
     */
    private function legacyUnitApprover(User $requester): array
    {
        if ($requester->approver_user_id === null) {
            throw ApprovalRoutingException::noUnitApprover($requester);
        }

        return [
            'status' => BookingStatus::Submitted,
            'chain' => [$this->delegations->resolve($requester->approver_user_id)],
            'approved_at' => null,
        ];
    }

    /**
     * @return array{status: BookingStatus, chain: list<int>, approved_at: ?Carbon}
     */
    private function legacyGaAdmin(): array
    {
        /** @var User|null $gaAdmin */
        $gaAdmin = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('code', 'ga_admin'))
            ->inRandomOrder()
            ->first();

        if ($gaAdmin === null) {
            throw ApprovalRoutingException::noGaAdmin();
        }

        return [
            'status' => BookingStatus::Submitted,
            'chain' => [$this->delegations->resolve($gaAdmin->id)],
            'approved_at' => null,
        ];
    }
}
