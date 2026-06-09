<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Booking calendar — daily room availability view.
 *
 * Time-grid by room on desktop (≥768px); list view on mobile (<768px).
 * Same component, two render branches in the blade view (M1-E adds mobile).
 *
 * Routed at GET /calendar (route name: calendar.index).
 *
 * Locked decisions (M1-D):
 *  Dec-1  Dynamic time window from room_operating_hours for visible rooms
 *  Dec-2  CSS Grid row-span for multi-slot bookings
 *  Dec-3  Booking blocks show subject + requester name
 *  Dec-4  Empty cell click → /bookings/new?room_id=X&starts_at=Y
 *  Dec-5  <input type="date"> + "Hari Ini" reset
 *  Dec-6  Pill row above grid for room toggle
 *  Dec-7  Empty state message + grid still rendered
 *
 * @property-read EloquentCollection<int, Room> $rooms
 * @property-read EloquentCollection<int, Room> $allActiveRooms
 * @property-read EloquentCollection<int, Booking> $bookings
 * @property-read array{open: ?string, close: ?string, slots: array<int, string>} $timeWindow
 * @property-read array<int, CarbonImmutable> $weekDays
 * @property-read array<int, array<int, array{date: CarbonImmutable, inMonth: bool}>> $monthGrid
 * @property-read array<string, array<int, Booking>> $bookingsByDay
 *
 * @see docs/m1-submit-ui-spec.md
 */
class BookingCalendar extends Component
{
    /**
     * Ultimate fallback timezone if both auth user timezone and
     * config('app.display_timezone') are unset. Resolution order:
     * (1) auth()->user()->timezone, (2) config('app.display_timezone'),
     * (3) this constant. Use $this->resolveTimezone() to get the actual value.
     */
    public const DISPLAY_TIMEZONE_FALLBACK = 'Asia/Jakarta';

    /**
     * Selected date in ISO 'YYYY-MM-DD' format, interpreted as local-day
     * in the resolved display timezone (per-user via auth()->user()->timezone
     * with fallback to config('app.display_timezone')).
     */
    #[Url(as: 'date')]
    public string $selectedDate = '';

    /**
     * Calendar view mode: 'day' (time-grid by room), 'week' (7-day agenda),
     * or 'month' (month grid). Persisted in the URL so a view survives refresh.
     */
    #[Url(as: 'view')]
    public string $view = 'day';

    /**
     * Room IDs to show. Empty array = show all active rooms (M1-D-Dec-3 default).
     *
     * @var array<int, int>
     */
    public array $roomFilterIds = [];

    /** @var array<int, string> */
    public const VIEWS = ['day', 'week', 'month'];

    public function mount(): void
    {
        $this->authorize('viewAny', Booking::class);

        if ($this->selectedDate === '') {
            $this->selectedDate = CarbonImmutable::now($this->resolveTimezone())
                ->format('Y-m-d');
        }

        if (! in_array($this->view, self::VIEWS, true)) {
            $this->view = 'day';
        }
    }

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) {
            $this->view = $view;
        }
    }

    /**
     * Jump to the day view for a specific date (used by week/month cell clicks).
     */
    public function goToDay(string $date): void
    {
        $this->selectedDate = CarbonImmutable::parse($date, $this->resolveTimezone())->format('Y-m-d');
        $this->view = 'day';
    }

    /** View-aware step backward (day / week / month). */
    public function previous(): void
    {
        $this->shift(-1);
    }

    /** View-aware step forward (day / week / month). */
    public function next(): void
    {
        $this->shift(1);
    }

    private function shift(int $direction): void
    {
        $date = CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone());

        $next = match ($this->view) {
            'week' => $date->addWeeks($direction),
            'month' => $date->addMonths($direction),
            default => $date->addDays($direction),
        };

        $this->selectedDate = $next->format('Y-m-d');
    }

    public function nextDay(): void
    {
        $this->selectedDate = CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone())
            ->addDay()
            ->format('Y-m-d');
    }

    public function previousDay(): void
    {
        $this->selectedDate = CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone())
            ->subDay()
            ->format('Y-m-d');
    }

    public function setToday(): void
    {
        $this->selectedDate = CarbonImmutable::now($this->resolveTimezone())->format('Y-m-d');
    }

    /**
     * Toggle a room in the filter. Empty result = "all rooms".
     */
    public function toggleRoom(int $roomId): void
    {
        if (in_array($roomId, $this->roomFilterIds, true)) {
            $this->roomFilterIds = array_values(array_filter(
                $this->roomFilterIds,
                fn (int $id): bool => $id !== $roomId
            ));
        } else {
            $this->roomFilterIds[] = $roomId;
        }
    }

    /**
     * Active rooms, optionally filtered by $roomFilterIds.
     *
     * @return EloquentCollection<int, Room>
     */
    #[Computed]
    public function rooms(): EloquentCollection
    {
        $query = Room::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('name');

        if (! empty($this->roomFilterIds)) {
            $query->whereIn('id', $this->roomFilterIds);
        }

        return $query->get(['id', 'code', 'name', 'capacity', 'location', 'floor']);
    }

    /**
     * All active rooms (unfiltered) — used for the filter pill row.
     *
     * @return EloquentCollection<int, Room>
     */
    #[Computed]
    public function allActiveRooms(): EloquentCollection
    {
        return Room::query()
            ->where('is_active', true)
            ->where('status', 'active')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * Bookings for selectedDate that lock slots (submitted + approved).
     * Eager-loaded with room + requester to avoid N+1.
     *
     * @return EloquentCollection<int, Booking>
     */
    #[Computed]
    public function bookings(): EloquentCollection
    {
        [$rangeStart, $rangeEnd] = $this->displayRange();
        $start = $rangeStart->utc();
        $end = $rangeEnd->utc();

        $roomIds = $this->rooms->pluck('id')->all();
        if (empty($roomIds)) {
            return new EloquentCollection;
        }

        return Booking::query()
            ->with(['room:id,name', 'requester:id,name'])
            ->whereIn('resource_id', $roomIds)
            ->whereIn('status', [BookingStatus::Submitted->value, BookingStatus::Approved->value])
            ->where(function ($q) use ($start, $end): void {
                // Booking overlaps the day if it starts before day end AND ends after day start
                $q->where('starts_at', '<', $end)
                    ->where('ends_at', '>', $start);
            })
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * The visible date range for the current view, in the display timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function displayRange(): array
    {
        $date = CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone());

        return match ($this->view) {
            'week' => [$date->startOfWeek(), $date->endOfWeek()],
            'month' => [$date->startOfMonth()->startOfWeek(), $date->endOfMonth()->endOfWeek()],
            default => [$date->startOfDay(), $date->endOfDay()],
        };
    }

    /**
     * The 7 days of the selected week (Mon–Sun), in the display timezone.
     *
     * @return array<int, CarbonImmutable>
     */
    #[Computed]
    public function weekDays(): array
    {
        $start = CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone())->startOfWeek();

        return array_map(fn (int $i): CarbonImmutable => $start->addDays($i), range(0, 6));
    }

    /**
     * The month grid as rows of 7 day-cells (leading/trailing days from
     * adjacent months included so each row is a full week).
     *
     * @return array<int, array<int, array{date: CarbonImmutable, inMonth: bool}>>
     */
    #[Computed]
    public function monthGrid(): array
    {
        $date = CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone());
        $month = $date->month;
        $cursor = $date->startOfMonth()->startOfWeek();
        $end = $date->endOfMonth()->endOfWeek();

        $weeks = [];
        $week = [];
        while ($cursor->lte($end)) {
            $week[] = ['date' => $cursor, 'inMonth' => $cursor->month === $month];
            if (count($week) === 7) {
                $weeks[] = $week;
                $week = [];
            }
            $cursor = $cursor->addDay();
        }

        return $weeks;
    }

    /**
     * Visible bookings grouped by their (display-tz) date 'Y-m-d'.
     * Used by the week + month views.
     *
     * @return array<string, array<int, Booking>>
     */
    #[Computed]
    public function bookingsByDay(): array
    {
        $tz = $this->resolveTimezone();
        $grouped = [];

        foreach ($this->bookings as $booking) {
            $key = CarbonImmutable::parse($booking->starts_at)->setTimezone($tz)->format('Y-m-d');
            $grouped[$key][] = $booking;
        }

        return $grouped;
    }

    /**
     * Compute the time window to render on the grid based on visible rooms'
     * operating hours for the selected day-of-week.
     *
     * Returns { open: '08:00', close: '17:00', slots: ['08:00', '08:30', ...] }
     * or { open: null, close: null, slots: [] } if all visible rooms closed.
     *
     * @return array{open: ?string, close: ?string, slots: array<int, string>}
     */
    #[Computed]
    public function timeWindow(): array
    {
        $dayOfWeek = (int) CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone())
            ->dayOfWeek;

        $roomIds = $this->rooms->pluck('id')->all();
        if (empty($roomIds)) {
            return ['open' => null, 'close' => null, 'slots' => []];
        }

        $hours = RoomOperatingHour::query()
            ->whereIn('room_id', $roomIds)
            ->where('day_of_week', $dayOfWeek)
            ->where('is_closed', false)
            ->whereNotNull('open_time')
            ->whereNotNull('close_time')
            ->get(['open_time', 'close_time']);

        if ($hours->isEmpty()) {
            return ['open' => null, 'close' => null, 'slots' => []];
        }

        $earliestOpen = $hours->min('open_time');
        $latestClose = $hours->max('close_time');

        return [
            'open' => substr((string) $earliestOpen, 0, 5),  // "HH:MM"
            'close' => substr((string) $latestClose, 0, 5),
            'slots' => $this->generateTimeSlots((string) $earliestOpen, (string) $latestClose),
        ];
    }

    /**
     * Generate 30-minute slot labels from open to close.
     * Excludes the close time itself (it's the end of the last slot).
     *
     * @return array<int, string>
     */
    private function generateTimeSlots(string $open, string $close): array
    {
        $current = CarbonImmutable::parse('1970-01-01 '.$open);
        $end = CarbonImmutable::parse('1970-01-01 '.$close);

        $slots = [];
        while ($current->lt($end)) {
            $slots[] = $current->format('H:i');
            $current = $current->addMinutes(30);
        }

        return $slots;
    }

    /**
     * Map a booking to its grid position: { rowStart, rowSpan } in 30-min slots.
     * Bookings extending beyond the visible window are clamped.
     *
     * @return array{rowStart: int, rowSpan: int}|null null if booking is entirely
     *                                                 outside the visible window
     */
    public function bookingGridPosition(Booking $booking): ?array
    {
        $window = $this->timeWindow;
        if (empty($window['slots'])) {
            return null;
        }

        // Convert booking UTC times to display TZ for grid alignment
        $bookingStart = CarbonImmutable::parse($booking->starts_at)
            ->setTimezone($this->resolveTimezone());
        $bookingEnd = CarbonImmutable::parse($booking->ends_at)
            ->setTimezone($this->resolveTimezone());

        $dayStart = CarbonImmutable::parse($this->selectedDate.' '.$window['open'], $this->resolveTimezone());
        $dayEnd = CarbonImmutable::parse($this->selectedDate.' '.$window['close'], $this->resolveTimezone());

        // Clamp booking to visible window
        $effectiveStart = $bookingStart->lt($dayStart) ? $dayStart : $bookingStart;
        $effectiveEnd = $bookingEnd->gt($dayEnd) ? $dayEnd : $bookingEnd;

        if ($effectiveStart->gte($dayEnd) || $effectiveEnd->lte($dayStart)) {
            return null;
        }

        $minutesFromOpen = (int) $dayStart->diffInMinutes($effectiveStart);
        $durationMinutes = (int) $effectiveStart->diffInMinutes($effectiveEnd);

        $rowStart = (int) floor($minutesFromOpen / 30) + 2; // +1 for 1-indexed CSS Grid, +1 for header row
        $rowSpan = max(1, (int) ceil($durationMinutes / 30));

        return ['rowStart' => $rowStart, 'rowSpan' => $rowSpan];
    }

    /**
     * Build URL params for an empty-cell click (M1-D-Dec-4).
     */
    public function emptyCellHref(int $roomId, string $slotLabel): string
    {
        $startsAt = $this->selectedDate.'T'.$slotLabel;

        return route('bookings.new', [
            'room_id' => $roomId,
            'starts_at' => $startsAt,
        ]);
    }

    /**
     * Format Carbon time in the resolved display timezone for booking blocks.
     */
    public function formatBookingTime(Booking $booking): string
    {
        $start = CarbonImmutable::parse($booking->starts_at)->setTimezone($this->resolveTimezone());
        $end = CarbonImmutable::parse($booking->ends_at)->setTimezone($this->resolveTimezone());

        return $start->format('H:i').'–'.$end->format('H:i');
    }

    public function render(): View
    {
        return view('livewire.booking.booking-calendar', [
            'rooms' => $this->rooms,
            'allRooms' => $this->allActiveRooms,
            'bookings' => $this->bookings,
            'timeWindow' => $this->timeWindow,
            'view' => $this->view,
            'weekDays' => $this->weekDays,
            'monthGrid' => $this->monthGrid,
            'bookingsByDay' => $this->bookingsByDay,
            'displayDate' => CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone())
                ->locale('id')
                ->isoFormat('dddd, D MMMM Y'),
            'rangeLabel' => $this->rangeLabel(),
        ])->layout('layouts.app', ['title' => __('Kalender'), 'subtitle' => __('Jadwal penggunaan ruang rapat')]);
    }

    /**
     * View-aware header label for the current range.
     */
    private function rangeLabel(): string
    {
        $locale = app()->getLocale();
        [$start, $end] = $this->displayRange();
        $date = CarbonImmutable::parse($this->selectedDate, $this->resolveTimezone())->locale($locale);

        return match ($this->view) {
            'week' => $start->locale($locale)->isoFormat('D MMM').' – '.$end->locale($locale)->isoFormat('D MMM Y'),
            'month' => $date->isoFormat('MMMM Y'),
            default => $date->isoFormat('dddd, D MMMM Y'),
        };
    }

    /**
     * Resolve the display timezone for the current request.
     * Per Blueprint Dec-09: prefer auth user's timezone, fall back to
     * APP_DISPLAY_TIMEZONE config, then to the class constant.
     */
    private function resolveTimezone(): string
    {
        $userTimezone = auth()->check() ? auth()->user()->timezone : null;

        return $userTimezone
            ?? config('app.display_timezone', self::DISPLAY_TIMEZONE_FALLBACK);
    }
}
