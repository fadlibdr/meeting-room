<?php

declare(strict_types=1);

namespace App\Livewire\FrontOffice;

use App\Actions\CheckInBookingAction;
use App\Models\Booking;
use App\Services\ActivityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Stage 4.1 — front-office daily view + manual check-in.
 *
 * The reception desk picks a day (default today) and sees that day's approved
 * bookings in chronological order, marking attendees in as they arrive. Gated by
 * bookings.check-in (front_office / ga_admin / super_admin).
 */
class DailyCheckIn extends Component
{
    public const DISPLAY_TIMEZONE_FALLBACK = 'Asia/Jakarta';

    #[Url(as: 'date', except: '')]
    public string $date = '';

    public string $feedback = '';

    public function mount(): void
    {
        if ($this->date === '') {
            $this->date = CarbonImmutable::now($this->resolveTimezone())->format('Y-m-d');
        }
    }

    public function checkIn(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);
        $this->authorize('checkIn', $booking);

        // Shared, idempotent check-in path (also used by QR self-check-in).
        app(CheckInBookingAction::class)->execute($booking, auth()->user());

        $this->feedback = __('Check-in berhasil dicatat.');
    }

    public function undoCheckIn(int $bookingId): void
    {
        $booking = Booking::findOrFail($bookingId);
        $this->authorize('checkIn', $booking);

        if ($booking->checked_in_at !== null) {
            $booking->forceFill(['checked_in_at' => null])->save();

            app(ActivityLogger::class)->log('bookings', 'check-in-undo', $booking, [
                'description' => sprintf('Pembatalan check-in untuk reservasi %s.', $booking->booking_code),
                'context' => ['booking_code' => $booking->booking_code],
            ]);
        }

        $this->feedback = __('Check-in dibatalkan.');
    }

    public function render(): View
    {
        return view('livewire.front-office.daily-check-in', [
            'bookings' => $this->bookingsForDay(),
            'timezone' => $this->resolveTimezone(),
        ])->layout('layouts.app', [
            'title' => __('Check-in Harian'),
            'subtitle' => __('Jadwal rapat hari ini dan check-in tamu'),
        ]);
    }

    /**
     * @return Collection<int, Booking>
     */
    private function bookingsForDay(): Collection
    {
        $tz = $this->resolveTimezone();

        $day = $this->parseDate($this->date, $tz) ?? CarbonImmutable::now($tz);
        $start = $day->startOfDay()->setTimezone('UTC');
        $end = $day->endOfDay()->setTimezone('UTC');

        return Booking::query()
            ->where('status', 'approved')
            ->whereBetween('starts_at', [$start, $end])
            ->with(['room:id,code,name,location', 'requester:id,name', 'requesterUnit:id,name'])
            ->orderBy('starts_at')
            ->get();
    }

    private function parseDate(string $value, string $tz): ?CarbonImmutable
    {
        if ($value === '') {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, $tz) ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveTimezone(): string
    {
        $userTimezone = auth()->check() ? auth()->user()->timezone : null;

        return $userTimezone ?? config('app.display_timezone', self::DISPLAY_TIMEZONE_FALLBACK);
    }
}
