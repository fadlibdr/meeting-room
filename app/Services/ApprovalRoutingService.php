<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\SubmitBookingAction;
use App\Actions\SubmitDraftAction;
use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Exceptions\ApprovalRoutingException;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Resolves a booking's initial approval state from a room's approval mode.
 *
 * Extracted from SubmitBookingAction (M3-C2) so the routing rule lives in
 * one place: SubmitBookingAction (new bookings) and SubmitDraftAction
 * (the Draft -> Submitted transition) both consume it. Per the Struktur
 * Proyek golden rule, a business rule is written once and called from
 * multiple actions.
 *
 * Pure routing: given a requester and a room approval mode, returns the
 * status / step / approver / approved_at the booking should take. No DB
 * writes and no locks — the calling action owns the transaction.
 *
 * @see SubmitBookingAction
 * @see SubmitDraftAction
 */
final class ApprovalRoutingService
{
    /**
     * Resolve the initial booking state for the given room approval mode.
     *
     * @return array{
     *     status: BookingStatus,
     *     current_step: ?int,
     *     approver_user_id: ?int,
     *     approved_at: ?Carbon
     * }
     *
     * @throws ApprovalRoutingException when no approver can be resolved
     */
    public function resolve(User $requester, RoomApprovalMode $mode): array
    {
        return match ($mode) {
            RoomApprovalMode::None => [
                'status' => BookingStatus::Approved,
                'current_step' => null,
                'approver_user_id' => null,
                'approved_at' => Carbon::now(),
            ],
            RoomApprovalMode::UnitApprover => $this->resolveUnitApprover($requester),
            RoomApprovalMode::GaAdmin => $this->resolveGaAdmin(),
        };
    }

    /**
     * @return array{status: BookingStatus, current_step: ?int, approver_user_id: ?int, approved_at: ?Carbon}
     */
    private function resolveUnitApprover(User $requester): array
    {
        if ($requester->approver_user_id === null) {
            throw ApprovalRoutingException::noUnitApprover($requester);
        }

        return [
            'status' => BookingStatus::Submitted,
            'current_step' => 1,
            'approver_user_id' => $requester->approver_user_id,
            'approved_at' => null,
        ];
    }

    /**
     * @return array{status: BookingStatus, current_step: ?int, approver_user_id: ?int, approved_at: ?Carbon}
     */
    private function resolveGaAdmin(): array
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
            'current_step' => 1,
            'approver_user_id' => $gaAdmin->id,
            'approved_at' => null,
        ];
    }
}
