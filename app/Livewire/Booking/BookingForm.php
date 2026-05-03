<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Models\Booking;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Booking submit form.
 *
 * Single-page Livewire component (D2). All fields visible at once.
 * Live conflict feedback (D3) wires in M1-C.
 *
 * Routed at GET /bookings/new (route name: bookings.new).
 *
 * M1-A status: skeleton only. mount() authorizes; render() returns placeholder view.
 * Real fields, validation, submit pipeline land in M1-B + M1-C.
 *
 * @see docs/m1-submit-ui-spec.md
 */
class BookingForm extends Component
{
    public function mount(): void
    {
        $this->authorize('create', Booking::class);
    }

    public function render(): View
    {
        return view('livewire.booking.booking-form')
            ->layout('layouts.app');
    }
}
