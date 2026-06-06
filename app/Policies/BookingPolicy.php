<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

/**
 * Authorization for Booking resource.
 *
 * Architectural decisions (Phase 2 Piece 1):
 * - Q1=A: Comprehensive — checks BOTH role/permission AND status state
 * - Q2=B: Simple pointer check — current_approver_user_id is source of truth (Dec-03)
 * - Q3=A: Permission-based — uses RBAC permissions, not direct role checks
 *
 * Permission semantics:
 * - bookings.view: see your own bookings
 * - bookings.view-all: see all bookings (read scope ONLY, not management)
 * - Specific actions (create, update, delete, etc.): require their explicit permission
 *
 * Important: view-all does NOT grant management authority. A user with
 * view-all can SEE bookings but cannot APPROVE/CANCEL/UPDATE others'
 * bookings unless they have the specific permission for that action.
 *
 * @see docs/sprint-2-plan.md
 */
class BookingPolicy
{
    /**
     * Determine whether the user can view the bookings list.
     *
     * Either bookings.view (own) or bookings.view-all (all) grants list access.
     * Filtering by ownership happens elsewhere (e.g. controller scope).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('bookings.view')
            || $user->hasPermission('bookings.view-all');
    }

    /**
     * Determine whether the user can view a specific booking.
     *
     * - Owner with bookings.view: yes
     * - Anyone with bookings.view-all: yes
     */
    public function view(User $user, Booking $booking): bool
    {
        if ($user->hasPermission('bookings.view-all')) {
            return true;
        }

        return $user->hasPermission('bookings.view')
            && $booking->requester_user_id === $user->id;
    }

    /**
     * Determine whether the user can add or remove attachments on the booking.
     *
     * Owner (with bookings.view) or a view-all admin, and only while the booking
     * is still active (draft / submitted / approved). Terminal bookings are
     * attachment read-only.
     */
    public function manageAttachments(User $user, Booking $booking): bool
    {
        if (! in_array($booking->status, [
            BookingStatus::Draft,
            BookingStatus::Submitted,
            BookingStatus::Approved,
        ], strict: true)) {
            return false;
        }

        if ($booking->requester_user_id === $user->id) {
            return $user->hasPermission('bookings.view');
        }

        return $user->hasPermission('bookings.view-all');
    }

    /**
     * Determine whether the user can create a booking.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('bookings.create');
    }

    /**
     * Determine whether the user can update the booking.
     *
     * Status guard: only Draft or Submitted are editable.
     * Permission guard: requires bookings.update.
     * Scope guard: own only (admins use view-all + update for cross-user edits).
     */
    public function update(User $user, Booking $booking): bool
    {
        if (! $this->isEditableStatus($booking)) {
            return false;
        }

        if (! $user->hasPermission('bookings.update')) {
            return false;
        }

        if ($booking->requester_user_id === $user->id) {
            return true;
        }

        // Cross-user update: needs view-all (admin scope) AND update permission
        return $user->hasPermission('bookings.view-all');
    }

    /**
     * Determine whether the user can hard-delete the booking.
     *
     * Per current matrix, only super_admin has bookings.delete. Drafts only.
     */
    public function delete(User $user, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Draft) {
            return false;
        }

        return $user->hasPermission('bookings.delete');
    }

    /**
     * Determine whether the user can submit the booking (draft → submitted).
     *
     * Owner-only operation: only the requester submits their own draft.
     */
    public function submit(User $user, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Draft) {
            return false;
        }

        if (! $user->hasPermission('bookings.submit')) {
            return false;
        }

        return $booking->requester_user_id === $user->id;
    }

    /**
     * Determine whether the user can cancel the booking.
     *
     * Cancellable: draft, submitted, approved (per Blueprint H.5).
     * Permission: requires bookings.cancel.
     * Scope: own bookings, OR admin with both view-all AND cancel.
     */
    public function cancel(User $user, Booking $booking): bool
    {
        if (! $this->isCancellableStatus($booking)) {
            return false;
        }

        if (! $user->hasPermission('bookings.cancel')) {
            return false;
        }

        if ($booking->requester_user_id === $user->id) {
            return true;
        }

        return $user->hasPermission('bookings.view-all');
    }

    /**
     * Determine whether the user can reschedule the booking (M3-Dec-2).
     *
     * Reschedule is "cancel + create new" (RescheduleBookingAction): only an
     * Approved booking can be rescheduled, and the user must additionally be
     * allowed to cancel it. cancel() already enforces the bookings.cancel
     * permission and the ownership / view-all scope, so reschedule() simply
     * layers the Approved-only status gate on top.
     */
    public function reschedule(User $user, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Approved) {
            return false;
        }

        // Owner path (existing): reuses the cancel gate (status + bookings.cancel + ownership).
        if ($this->cancel($user, $booking)) {
            return true;
        }

        // Admin path (Stage 2.1.3): a manager with org-wide booking visibility
        // (e.g. GA Admin) may reschedule others' approved bookings, even without
        // the bookings.cancel permission.
        return $user->hasPermission('bookings.view-all')
            || $user->hasPermission('bookings.override');
    }

    /**
     * Determine whether the user can manually check in the booking (Stage 4.1).
     *
     * Front-office reception operation: only an Approved booking can be checked
     * in, and only by a holder of bookings.check-in (front_office / ga_admin /
     * super_admin). Scope is org-wide by design — the desk checks anyone in.
     */
    public function checkIn(User $user, Booking $booking): bool
    {
        return $booking->status === BookingStatus::Approved
            && $user->hasPermission('bookings.check-in');
    }

    /**
     * Determine whether the user can approve the booking.
     *
     * Permission gate: requires bookings.approve.
     * Status gate: must be Submitted.
     * Scope gate: assigned approver via current_approver_user_id.
     *
     * Note: view-all does NOT grant approval. A unit_approver who can
     * view-all bookings cannot approve bookings they're not assigned to.
     * Only ga_admin (with explicit cross-unit scope per business rules)
     * uses approve permission with view-all.
     *
     * The actual gate that lets ga_admin approve any booking is the
     * combination of: bookings.approve + view-all + current_approver pointer
     * being unset OR pointing somewhere ga_admin can override.
     *
     * For correctness with current seeder: only assigned approvers can
     * approve. ga_admin assignment happens at submission time (room with
     * approval_mode=ga_admin assigns to a ga_admin user).
     */
    public function approve(User $user, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Submitted) {
            return false;
        }

        if (! $user->hasPermission('bookings.approve')) {
            return false;
        }
        if ($user->hasPermission('bookings.override')) {
            return true;
        }

        return $booking->current_approver_user_id === $user->id;
    }

    /**
     * Determine whether the user can reject the booking.
     *
     * Same gates as approve.
     */
    public function reject(User $user, Booking $booking): bool
    {
        if ($booking->status !== BookingStatus::Submitted) {
            return false;
        }

        if (! $user->hasPermission('bookings.reject')) {
            return false;
        }
        if ($user->hasPermission('bookings.override')) {
            return true;
        }

        return $booking->current_approver_user_id === $user->id;
    }

    /**
     * A booking is editable in Draft or Submitted status.
     */
    private function isEditableStatus(Booking $booking): bool
    {
        return in_array($booking->status, [
            BookingStatus::Draft,
            BookingStatus::Submitted,
        ], strict: true);
    }

    /**
     * A booking is cancellable in Draft, Submitted, or Approved status.
     */
    private function isCancellableStatus(Booking $booking): bool
    {
        return in_array($booking->status, [
            BookingStatus::Draft,
            BookingStatus::Submitted,
            BookingStatus::Approved,
        ], strict: true);
    }
}
