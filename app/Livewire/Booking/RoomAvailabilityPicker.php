<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use Illuminate\View\View;
use Livewire\Attributes\Reactive;
use Livewire\Component;

/**
 * Visual room picker — nested inside BookingForm.
 *
 * Receives starts_at + ends_at from parent BookingForm (reactive properties).
 * Renders rooms as cards with availability badges for the selected window.
 * Click a room → emit room-selected event; parent listens and sets $roomId.
 *
 * NOT a routed component — only used as a child:
 *   <livewire:booking.room-availability-picker :starts-at="..." :ends-at="..." />
 *
 * M1-A status: skeleton only. Real availability rendering in M1-F.
 *
 * @see docs/m1-submit-ui-spec.md
 */
class RoomAvailabilityPicker extends Component
{
    #[Reactive]
    public ?string $startsAt = null;

    #[Reactive]
    public ?string $endsAt = null;

    public ?int $selectedRoomId = null;

    public function render(): View
    {
        return view('livewire.booking.room-availability-picker');
    }
}
