<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Services\RoomUtilizationReport;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Stage 2.1d — Room utilization dashboard.
 *
 * Read-only analytics surface gated by reports.view. Lets a manager pick a date
 * range (or quick preset) and see per-room utilization, peak-hour occupancy, and
 * per-unit demand. All heavy lifting lives in {@see RoomUtilizationReport}; this
 * component only resolves the display timezone and the selected window.
 */
class UtilizationDashboard extends Component
{
    /** Per-user timezone fallback, mirroring BookingCalendar. */
    public const DISPLAY_TIMEZONE_FALLBACK = 'Asia/Jakarta';

    public string $from = '';

    public string $to = '';

    /** Active quick-range preset in days, or null when using custom dates. */
    public ?int $preset = 30;

    public function mount(): void
    {
        $tz = $this->resolveTimezone();
        $today = CarbonImmutable::now($tz);

        $this->to = $today->format('Y-m-d');
        $this->from = $today->subDays(29)->format('Y-m-d');
    }

    public function applyPreset(int $days): void
    {
        $tz = $this->resolveTimezone();
        $today = CarbonImmutable::now($tz);

        $this->preset = $days;
        $this->to = $today->format('Y-m-d');
        $this->from = $today->subDays($days - 1)->format('Y-m-d');
    }

    public function updatedFrom(): void
    {
        $this->preset = null;
    }

    public function updatedTo(): void
    {
        $this->preset = null;
    }

    /**
     * @return array<string, mixed>
     */
    #[Computed]
    public function report(): array
    {
        $tz = $this->resolveTimezone();

        $from = $this->parseDate($this->from, $tz)
            ?? CarbonImmutable::now($tz)->subDays(29)->startOfDay();
        $to = $this->parseDate($this->to, $tz)
            ?? CarbonImmutable::now($tz)->endOfDay();

        // Guard against an inverted range (user picks to < from).
        if ($to < $from) {
            [$from, $to] = [$to, $from];
        }

        return app(RoomUtilizationReport::class, ['displayTimezone' => $tz])
            ->build($from, $to);
    }

    public function render(): View
    {
        return view('livewire.admin.utilization-dashboard')
            ->layout('layouts.app', [
                'title' => __('Laporan Utilisasi'),
                'subtitle' => __('Tingkat pemanfaatan ruang rapat'),
            ]);
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

        return $userTimezone
            ?? config('app.display_timezone', self::DISPLAY_TIMEZONE_FALLBACK);
    }
}
