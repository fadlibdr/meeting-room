<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CancelBookingAction;
use App\Actions\CancelRecurringBookingAction;
use App\Actions\DeleteBookingAction;
use App\Actions\SubmitBookingAction;
use App\Actions\SubmitDraftAction;
use App\Enums\BookingStatus;
use App\Exceptions\ApprovalRoutingException;
use App\Exceptions\BookingConflictException;
use App\Http\Requests\Booking\CancelBookingRequest;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\User;
use App\Services\IcsGenerator;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Thin HTTP entry point for booking operations.
 *
 * Sprint 2B scope: store() only — the integration target for the
 * full Form Request → Policy → Action stack. Other resource methods
 * (index, show, edit, update, destroy) belong to Sprint 2C alongside
 * their Blade/Livewire views.
 *
 * Production traffic in Sprint 2C+ will primarily go through Livewire
 * BookingForm. This controller exists so the same backend logic is
 * reachable via plain HTTP — useful for integration tests and as a
 * fallback for non-JS clients.
 */
class BookingController extends Controller
{
    /**
     * Submit a new booking.
     *
     * Authorization is enforced by StoreBookingRequest::authorize()
     * which delegates to BookingPolicy::create.
     *
     * Validation is enforced by StoreBookingRequest::rules() and
     * withValidator() callbacks (duration, capacity, room active).
     *
     * Domain exceptions thrown by SubmitBookingAction are converted
     * into field-mapped validation errors so the form can re-render
     * inline messages near the offending input.
     *
     * NOTE: Livewire BookingForm in Sprint 2C will catch these
     * exceptions directly within the component and decide its own
     * presentation — this field-mapping is for plain HTTP form posts only.
     */
    public function store(
        StoreBookingRequest $request,
        SubmitBookingAction $action,
    ): RedirectResponse {
        try {
            /** @var User $user */
            $user = $request->user();
            $booking = $action->execute($user, $request->validated());
        } catch (BookingConflictException $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'starts_at' => 'Slot waktu yang dipilih bentrok dengan jadwal lain. Silakan pilih waktu lain.',
                ]);
        } catch (ApprovalRoutingException $e) {
            return back()
                ->withInput()
                ->withErrors([
                    'resource_id' => $e->getMessage(),
                ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', "Booking {$booking->booking_code} berhasil dibuat.");
    }

    /**
     * Cancel a booking (Draft / Submitted / Approved -> Cancelled).
     *
     * Authorization + the conditionally-required reason are enforced
     * by CancelBookingRequest (-> BookingPolicy::cancel, Blueprint
     * H.5). CancelBookingAction re-checks status under a row lock; a
     * DomainException (booking no longer cancellable - a race with
     * another transition) or InvalidArgumentException is surfaced as
     * a non-field 'cancel' error so the show page renders gracefully.
     */
    public function cancel(
        CancelBookingRequest $request,
        Booking $booking,
        CancelBookingAction $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = $request->user();

        /** @var string|null $reason */
        $reason = $request->validated('cancellation_reason');

        try {
            $action->execute($booking, $user, $reason);
        } catch (DomainException|InvalidArgumentException $e) {
            return back()->withErrors(['cancel' => $e->getMessage()]);
        }

        return redirect()
            ->route('bookings.show', $booking->id)
            ->with('success', "Reservasi {$booking->booking_code} berhasil dibatalkan.");
    }

    /**
     * Cancel every still-cancellable occurrence in this booking's recurring
     * series. Route-authorized by can:cancel,booking. A reason is required so
     * that approved occurrences (which CancelBookingAction refuses to cancel
     * without one) are not silently skipped.
     */
    public function cancelSeries(
        Request $request,
        Booking $booking,
        CancelRecurringBookingAction $action,
    ): RedirectResponse {
        $validated = $request->validate(
            ['cancellation_reason' => ['required', 'string', 'max:2000']],
            ['cancellation_reason.required' => 'Alasan pembatalan wajib diisi untuk membatalkan seri.'],
        );

        /** @var User $user */
        $user = $request->user();

        $count = $action->execute($booking, $user, $validated['cancellation_reason']);

        return redirect()
            ->route('bookings.show', $booking->id)
            ->with('success', "Seri reservasi dibatalkan: {$count} jadwal.");
    }

    /**
     * Submit a Draft booking for approval (Draft -> Submitted/Approved).
     *
     * Authorization is handled by the route-level can:submit,booking
     * middleware (routes/web.php) -> BookingPolicy::submit (Draft only).
     *
     * SubmitDraftAction re-checks the slot under a row lock: a Draft
     * never held the slot, so it may have been taken since the draft
     * was saved (M3-Dec-12). A BookingConflictException, a routing
     * failure, or a DomainException (the booking is no longer Draft -
     * a race) is surfaced as a non-field 'submit' error.
     */
    public function submit(
        Booking $booking,
        SubmitDraftAction $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = auth()->user();

        try {
            $action->execute($booking, $user);
        } catch (BookingConflictException $e) {
            return back()->withErrors([
                'submit' => 'Slot waktu reservasi ini sudah tidak tersedia. Silakan ubah jadwal lalu ajukan kembali.',
            ]);
        } catch (ApprovalRoutingException|DomainException $e) {
            return back()->withErrors(['submit' => $e->getMessage()]);
        }

        return redirect()
            ->route('bookings.show', $booking->id)
            ->with('success', "Reservasi {$booking->booking_code} berhasil diajukan.");
    }

    /**
     * Permanently delete a Draft booking (M3-F, M3-Dec-4).
     *
     * Authorization is handled by the route-level can:delete,booking
     * middleware (routes/web.php) -> BookingPolicy::delete (Draft only).
     * That gate already rejects every non-Draft booking with a 403, so
     * the DomainException catch below only fires on the rare race where
     * the booking left Draft between route authorization and the
     * action's row lock.
     *
     * The booking row is gone afterwards, so the redirect target is the
     * calendar, not bookings.show.
     */
    public function destroy(
        Booking $booking,
        DeleteBookingAction $action,
    ): RedirectResponse {
        /** @var User $user */
        $user = auth()->user();

        $bookingCode = $booking->booking_code;

        try {
            $action->execute($booking, $user);
        } catch (DomainException $e) {
            return back()->withErrors(['delete' => $e->getMessage()]);
        }

        return redirect()
            ->route('calendar.index')
            ->with('success', "Reservasi draf {$bookingCode} berhasil dihapus.");
    }

    /**
     * Display a single booking with its approval timeline.
     *
     * Authorization is handled by the route-level can:view,booking
     * middleware (routes/web.php) -> BookingPolicy::view. No
     * authorization code is needed in this method.
     */
    public function show(Booking $booking): View
    {
        $booking->load([
            'room',
            'requester',
            'requesterUnit',
            'currentApprover',
            'attachments',
        ]);

        return view('bookings.show', [
            'booking' => $booking,
            'timeline' => $this->buildTimeline($booking),
        ]);
    }

    /**
     * Download the booking as an RFC 5545 .ics calendar file.
     * Route-authorized by can:view,booking.
     */
    public function calendar(Booking $booking, IcsGenerator $ics): Response
    {
        return response($ics->forBooking($booking), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$ics->filename($booking).'"',
        ]);
    }

    /**
     * Merge status history and acted approvals into one chronological
     * timeline (2D-D-Dec-4). Pending approvals are excluded -- they are
     * current state, surfaced in the page header, not history.
     *
     * Each source is queried from its model class so element types are
     * statically resolvable.
     */
    private function buildTimeline(Booking $booking): Collection
    {
        /** @var list<array<string, mixed>> $entries */
        $entries = [];

        $histories = BookingStatusHistory::query()
            ->where('booking_id', $booking->id)
            ->with('changedBy')
            ->get();

        foreach ($histories as $history) {
            $entries[] = [
                'at' => $history->changed_at,
                'type' => 'status',
                'title' => 'Status: '.(BookingStatus::tryFrom($history->to_status)?->label() ?? $history->to_status),
                'detail' => $history->change_reason,
                'actor' => $this->actorName($history->changedBy),
            ];
        }

        $approvals = BookingApproval::query()
            ->where('booking_id', $booking->id)
            ->whereNotNull('action_at')
            ->with(['actedBy', 'approver'])
            ->get();

        foreach ($approvals as $approval) {
            $entries[] = [
                'at' => $approval->action_at,
                'type' => 'approval',
                'title' => 'Keputusan approval: '.$this->approvalStatusLabel($approval->status),
                'detail' => $approval->action_notes,
                'actor' => $this->actorName($approval->actedBy) ?? $this->actorName($approval->approver),
            ];
        }

        return collect($entries)->sortBy('at')->values();
    }

    /**
     * Indonesian label for a booking_approvals.status value.
     * (That column is a plain string, not an enum.)
     */
    private function approvalStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => 'Menunggu',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'skipped' => 'Dilewati',
            'cancelled' => 'Dibatalkan',
            default => $status,
        };
    }

    /**
     * Display name of a related user model, or null if absent.
     *
     * Uses getAttribute() so the call is valid even when static
     * analysis resolves the relation only to the base Model type.
     */
    private function actorName(?Model $user): ?string
    {
        if ($user === null) {
            return null;
        }

        $name = $user->getAttribute('name');

        return is_string($name) ? $name : null;
    }
}
