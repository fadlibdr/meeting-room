<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Models\Booking;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Booking calendar — daily room availability view.
 *
 * Time-grid by room on desktop (≥768px), list view on mobile (<768px).
 * Same component, two render branches in the blade view.
 *
 * Routed at GET /calendar (route name: calendar.index).
 *
 * M1-A status: skeleton only. mount() authorizes; render() returns placeholder view.
 * Date navigation, filters, time-grid layout land in M1-D + M1-E.
 *
 * @see docs/m1-submit-ui-spec.md
 */
class BookingCalendar extends Component
{
    public function mount(): void
    {
        $this->authorize('viewAny', Booking::class);
    }

    public function render(): View
    {
        return view('livewire.booking.booking-calendar')
            ->layout('layouts.app');
    }
}
