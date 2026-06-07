<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\DataTransferObjects\ConflictItem;
use App\Models\Resource;
use App\Services\BookingConflictService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Reactive;
use Livewire\Component;
use Throwable;

/**
 * Visual room picker — nested inside BookingForm.
 *
 * Receives starts_at / ends_at / attendee_count / selected_room_id from
 * parent BookingForm as #[Reactive] props. Renders all active rooms as
 * cards with availability badges computed for the selected window.
 *
 * Click a room card → dispatches 'room-selected' event with roomId payload.
 * Parent BookingForm listens via #[On('room-selected')] and updates its
 * own $roomId, then triggers conflict re-check. Picker is stateless
 * (single source of truth = parent), just reflects parent's selection.
 *
 * NOT a routed component. Used as a child:
 *   <livewire:booking.room-availability-picker
 *       :starts-at="$startsAt" :ends-at="$endsAt"
 *       :attendee-count="$attendeeCount" :selected-room-id="(int) $roomId" />
 *
 * Per-room availability strategy (M1-F-Dec-1=A): loops over rooms calling
 * BookingConflictService::findConflicts once per room. With current scale
 * (~8 rooms) and indexed queries (~0.5-2ms each), this stays under 20ms
 * total per debounced recalc — well within debounce window.
 *
 * Locked decisions (M1-F):
 *  Dec-1=A  Per-room loop (revised; simpler than batch)
 *  Dec-2=1  Dispatch event to parent (standard Livewire 3)
 *  Dec-3=B  3-column grid at md+ (denser scan)
 *  Dec-4=A  Neutral cards + hint when window unset
 *  Dec-5=B  Advisory tint on capacity overflow (allow selection)
 *  Dec-6=A  Replace <select> entirely (cards are the only input)
 *
 * @property-read EloquentCollection<int, \App\Models\Resource> $rooms
 * @property-read array<int, array{status: string, conflictTitle: ?string, exceedsCapacity: bool}> $availability
 *
 * @see docs/m1-submit-ui-spec.md
 */
class RoomAvailabilityPicker extends Component
{
    /** Datetime-local formatted string from parent. Empty = window not yet set. */
    #[Reactive]
    public string $startsAt = '';

    #[Reactive]
    public string $endsAt = '';

    #[Reactive]
    public int $attendeeCount = 1;

    /** Currently selected room ID (mirrors parent's $roomId cast to int; 0 = none). */
    #[Reactive]
    public int $selectedRoomId = 0;

    /** Booking to exclude from conflict checks - set by BookingForm edit mode (M3-C). */
    #[Reactive]
    public ?int $excludeBookingId = null;

    /** Which resource type to show (room/equipment/vehicle/desk) — Stage 3 E2c. */
    #[Reactive]
    public string $resourceType = 'room';

    /**
     * User clicked a room card. Dispatch event up to parent which handles
     * actual state mutation. Picker stays stateless re: selection.
     */
    public function selectRoom(int $roomId): void
    {
        $this->dispatch('room-selected', roomId: $roomId);
    }

    /**
     * All active resources of the selected type, ordered by name.
     *
     * @return EloquentCollection<int, \App\Models\Resource>
     */
    #[Computed]
    public function rooms(): EloquentCollection
    {
        return Resource::query()
            ->where('type', $this->resourceType)
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'capacity', 'location', 'floor', 'approval_mode']);
    }

    /**
     * Availability map: roomId => { status, conflictTitle, exceedsCapacity }
     *
     * status values:
     *   'unknown'   → window not set or invalid; show neutral card
     *   'available' → no conflicts for this room in this window
     *   'unavailable' → at least one conflict; conflictTitle has the first one
     *
     * exceedsCapacity is independent of status (advisory, not blocking).
     *
     * @return array<int, array{status: string, conflictTitle: ?string, exceedsCapacity: bool}>
     */
    #[Computed]
    public function availability(): array
    {
        $rooms = $this->rooms;
        $result = [];

        // Window not set → all rooms unknown (Dec-4=A: neutral cards w/ hint)
        if ($this->startsAt === '' || $this->endsAt === '') {
            foreach ($rooms as $room) {
                $result[$room->id] = [
                    'status' => 'unknown',
                    'conflictTitle' => null,
                    'exceedsCapacity' => $this->attendeeCount > $room->capacity,
                ];
            }

            return $result;
        }

        // Try to parse window; bail to unknown on any parse error
        try {
            $startsAt = CarbonImmutable::parse($this->normalizeDatetime($this->startsAt))->utc();
            $endsAt = CarbonImmutable::parse($this->normalizeDatetime($this->endsAt))->utc();

            if ($endsAt->lessThanOrEqualTo($startsAt)) {
                throw new \InvalidArgumentException('endsAt must be after startsAt');
            }
        } catch (Throwable) {
            foreach ($rooms as $room) {
                $result[$room->id] = [
                    'status' => 'unknown',
                    'conflictTitle' => null,
                    'exceedsCapacity' => $this->attendeeCount > $room->capacity,
                ];
            }

            return $result;
        }

        // Per-room loop calling existing service. Each call is one indexed query.
        $service = app(BookingConflictService::class);

        foreach ($rooms as $room) {
            $conflicts = $service->findConflicts($room, $startsAt, $endsAt, $this->excludeBookingId);

            if ($conflicts->isEmpty()) {
                $result[$room->id] = [
                    'status' => 'available',
                    'conflictTitle' => null,
                    'exceedsCapacity' => $this->attendeeCount > $room->capacity,
                ];
            } else {
                /** @var ConflictItem $first */
                $first = $conflicts->first();
                $result[$room->id] = [
                    'status' => 'unavailable',
                    'conflictTitle' => $first->title,
                    'exceedsCapacity' => $this->attendeeCount > $room->capacity,
                ];
            }
        }

        return $result;
    }

    /**
     * Convert datetime-local input ('2026-05-05T10:00') to format Carbon parses
     * unambiguously. Mirrors BookingForm::normalizeDatetime().
     */
    private function normalizeDatetime(string $value): string
    {
        $normalized = str_replace('T', ' ', trim($value));

        // Datetime-local omits seconds — append :00 if only one colon
        if (substr_count($normalized, ':') === 1) {
            $normalized .= ':00';
        }

        return $normalized;
    }

    public function render(): View
    {
        return view('livewire.booking.room-availability-picker', [
            'rooms' => $this->rooms,
            'availability' => $this->availability,
        ]);
    }
}
