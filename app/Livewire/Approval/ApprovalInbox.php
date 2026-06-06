<?php

declare(strict_types=1);

namespace App\Livewire\Approval;

use App\Actions\ApproveBookingAction;
use App\Actions\RejectBookingAction;
use App\Enums\BookingStatus;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\User;
use DomainException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Approval inbox — the signed-in approver's queue of bookings awaiting
 * their decision (current_approver_user_id = this user, status =
 * Submitted).
 *
 * Full-page Livewire component, routed at GET /approvals
 * (permission:bookings.approve). Inline approve / reject; reject
 * requires a reason. All business rules live in ApproveBookingAction /
 * RejectBookingAction — this component only authorizes and delegates
 * (Struktur Proyek Laravel v2, Section E.3).
 *
 * @see ApproveBookingAction
 * @see RejectBookingAction
 */
class ApprovalInbox extends Component
{
    /** Booking id whose inline reject form is open; null = none open. */
    public ?int $rejectingId = null;

    /** Reason typed into the open reject form. */
    public string $rejectReason = '';

    /** Post-action feedback banner message; null = no banner. */
    public ?string $feedback = null;

    /** Feedback banner style: 'success' or 'error'. */
    public string $feedbackType = 'success';

    public function approve(int $bookingId, ApproveBookingAction $action): void
    {
        $this->feedback = null;

        $booking = Booking::findOrFail($bookingId);
        $this->authorize('approve', $booking);

        /** @var User $user */
        $user = auth()->user();

        try {
            $action->execute($booking, $user);
        } catch (BookingConflictException $e) {
            $this->feedback = 'Booking tidak dapat disetujui: slot waktu sudah '
                .'bentrok dengan jadwal lain. Daftar telah diperbarui.';
            $this->feedbackType = 'error';

            return;
        } catch (DomainException $e) {
            $this->feedback = 'Booking ini sudah tidak menunggu persetujuan. '
                .'Daftar telah diperbarui.';
            $this->feedbackType = 'error';

            return;
        }

        $this->feedback = "Booking {$booking->booking_code} telah disetujui.";
        $this->feedbackType = 'success';
    }

    public function startReject(int $bookingId): void
    {
        $this->rejectingId = $bookingId;
        $this->rejectReason = '';
        $this->feedback = null;
    }

    public function cancelReject(): void
    {
        $this->rejectingId = null;
        $this->rejectReason = '';
    }

    public function reject(RejectBookingAction $action): void
    {
        $this->feedback = null;

        $this->validate(
            ['rejectReason' => ['required', 'string', 'min:3', 'max:1000']],
            [
                'rejectReason.required' => 'Alasan penolakan wajib diisi.',
                'rejectReason.min' => 'Alasan penolakan terlalu singkat.',
            ]
        );

        $booking = Booking::findOrFail((int) $this->rejectingId);
        $this->authorize('reject', $booking);

        /** @var User $user */
        $user = auth()->user();

        try {
            $action->execute($booking, $user, $this->rejectReason);
        } catch (DomainException $e) {
            $this->feedback = 'Booking ini sudah tidak menunggu persetujuan. '
                .'Daftar telah diperbarui.';
            $this->feedbackType = 'error';
            $this->cancelReject();

            return;
        }

        $this->feedback = "Booking {$booking->booking_code} telah ditolak.";
        $this->feedbackType = 'success';
        $this->cancelReject();
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();

        $pendingBookings = Booking::query()
            ->where('current_approver_user_id', $user->id)
            ->where('status', BookingStatus::Submitted)
            ->with(['room', 'requester'])
            ->orderBy('starts_at')
            ->get();

        return view('livewire.approval.approval-inbox', [
            'pendingBookings' => $pendingBookings,
        ])->layout('layouts.app', ['title' => __('Persetujuan'), 'subtitle' => __('Reservasi menunggu persetujuan Anda')]);
    }
}
