<?php

declare(strict_types=1);

namespace App\Http\Requests\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for booking cancellation (M3 Phase B).
 *
 * - Authorization: the current user may cancel this booking
 *   (BookingPolicy::cancel — owner with bookings.cancel, or an admin
 *   with view-all; only Draft / Submitted / Approved are cancellable).
 * - Input: cancellation_reason — required when the booking is Approved
 *   (Blueprint H.5), optional otherwise. CancelBookingAction enforces
 *   the same rule independently as defense-in-depth.
 */
class CancelBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');

        return $booking instanceof Booking
            && ($this->user()?->can('cancel', $booking) ?? false);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $booking = $this->route('booking');
        $reasonRequired = $booking instanceof Booking
            && $booking->status === BookingStatus::Approved;

        return [
            'cancellation_reason' => [
                $reasonRequired ? 'required' : 'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cancellation_reason.required' => 'Alasan pembatalan wajib diisi untuk reservasi yang sudah disetujui.',
            'cancellation_reason.string' => 'Alasan pembatalan harus berupa teks.',
            'cancellation_reason.max' => 'Alasan pembatalan maksimal 1000 karakter.',
        ];
    }
}
