<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SubmitBookingAction;
use App\Exceptions\ApprovalRoutingException;
use App\Exceptions\BookingConflictException;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

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
                    'room_id' => $e->getMessage(),
                ]);
        }

        return redirect()
            ->route('dashboard')
            ->with('success', "Booking {$booking->booking_code} berhasil dibuat.");
    }
}
