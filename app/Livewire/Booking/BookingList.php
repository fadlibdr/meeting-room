<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BookingCsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bookings list — the signed-in user's bookings, or all bookings for users
 * holding bookings.view-all. Full-page Livewire (mirrors BookingCalendar /
 * ApprovalInbox), routed at GET /bookings (can:viewAny,Booking).
 *
 * Scope follows BookingPolicy::view: view-all => every booking; otherwise
 * restricted to requester_user_id = the current user. Read + navigate only;
 * state changes (submit/cancel/reschedule/delete) live on the show page.
 */
class BookingList extends Component
{
    use WithPagination;

    public const DISPLAY_TIMEZONE_FALLBACK = 'Asia/Jakarta';

    #[Url(as: 'status', except: '')]
    public string $statusFilter = '';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    public function mount(): void
    {
        $this->authorize('viewAny', Booking::class);
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset(['statusFilter', 'search', 'dateFrom', 'dateTo']);
        $this->resetPage();
    }

    public function export(ActivityLogger $logger, BookingCsvExporter $exporter): StreamedResponse
    {
        $this->authorize('viewAny', Booking::class);

        /** @var User $user */
        $user = auth()->user();
        $canViewAll = $user->hasPermission('bookings.view-all');
        $timezone = $this->resolveTimezone();

        $rowCount = $this->baseQuery($user, $canViewAll)->count();

        $logger->log('bookings', 'export', null, [
            'description' => sprintf('%s mengekspor %d data booking ke CSV.', $user->name, $rowCount),
            'context' => [
                'format' => 'csv',
                'row_count' => $rowCount,
                'scope' => $canViewAll ? 'all' : 'own',
                'filters' => array_filter([
                    'status' => $this->statusFilter,
                    'search' => $this->search,
                    'date_from' => $this->dateFrom,
                    'date_to' => $this->dateTo,
                ], static fn (string $value): bool => $value !== ''),
            ],
        ]);

        $filename = 'bookings-export-'.now()->format('Ymd-His').'.csv';
        $bookings = $this->baseQuery($user, $canViewAll)
            ->with('requesterUnit')
            ->cursor();

        return response()->streamDownload(
            function () use ($exporter, $bookings, $timezone): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                $exporter->writeCsv($handle, $bookings, $timezone);
                fclose($handle);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    public function render(): View
    {
        /** @var User $user */
        $user = auth()->user();
        $canViewAll = $user->hasPermission('bookings.view-all');

        return view('livewire.booking.booking-list', [
            'bookings' => $this->buildQuery($user, $canViewAll),
            'canViewAll' => $canViewAll,
            'canCreate' => $user->hasPermission('bookings.create'),
            'statuses' => BookingStatus::cases(),
        ])->layout('layouts.app', ['title' => 'Reservasi', 'subtitle' => 'Daftar pemesanan ruang rapat']);
    }

    /**
     * @return LengthAwarePaginator<int, Booking>
     */
    private function buildQuery(User $user, bool $canViewAll): LengthAwarePaginator
    {
        return $this->baseQuery($user, $canViewAll)->paginate(15);
    }

    /**
     * Scope + filter logic shared by the list (render) and the CSV export.
     * Returns the query builder pre-pagination.
     *
     * @return Builder<Booking>
     */
    private function baseQuery(User $user, bool $canViewAll): Builder
    {
        $query = Booking::query()
            ->with(['room', 'requester'])
            ->orderByDesc('starts_at');

        if (! $canViewAll) {
            $query->where('requester_user_id', $user->id);
        }

        if ($this->statusFilter !== '') {
            $query->where('status', $this->statusFilter);
        }

        if ($this->search !== '') {
            $term = '%'.$this->search.'%';
            $query->where(function ($q) use ($term): void {
                $q->where('subject', 'like', $term)
                    ->orWhere('booking_code', 'like', $term);
            });
        }

        if ($this->dateFrom !== '') {
            $from = $this->tryParse($this->dateFrom);
            if ($from !== null) {
                $query->where('starts_at', '>=', $from->startOfDay());
            }
        }

        if ($this->dateTo !== '') {
            $to = $this->tryParse($this->dateTo);
            if ($to !== null) {
                $query->where('starts_at', '<=', $to->endOfDay());
            }
        }

        return $query;
    }

    public function displayDateTime(Booking $booking): string
    {
        $tz = $this->resolveTimezone();
        $start = $booking->starts_at->copy()->setTimezone($tz);
        $end = $booking->ends_at->copy()->setTimezone($tz);

        return $start->locale('id')->isoFormat('ddd, D MMM Y').' · '
            .$start->format('H:i').'–'.$end->format('H:i');
    }

    private function tryParse(string $value): ?Carbon
    {
        try {
            return Carbon::parse($value);
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
