<?php

declare(strict_types=1);

namespace App\Livewire\Booking;

use App\Enums\BookingStatus;
use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Jobs\GenerateBookingExportJob;
use App\Models\Booking;
use App\Models\Export;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\BookingCsvExporter;
use App\Services\BookingExportQuery;
use App\Services\BookingXlsxExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
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

    /** Exports above this row count are generated off-cycle via a queued job. */
    public const SYNC_EXPORT_LIMIT = 1000;

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

    public function export(
        string $format,
        ActivityLogger $logger,
        BookingCsvExporter $csv,
        BookingXlsxExporter $xlsx,
    ): StreamedResponse|BinaryFileResponse|null {
        $this->authorize('viewAny', Booking::class);

        $exportFormat = ExportFormat::tryFrom($format) ?? ExportFormat::Csv;

        /** @var User $user */
        $user = auth()->user();
        $canViewAll = $user->hasPermission('bookings.view-all');
        $timezone = $this->resolveTimezone();
        $filters = $this->exportFilters();

        $rowCount = $this->baseQuery($user, $canViewAll)->count();

        $logger->log('bookings', 'export', null, [
            'description' => sprintf('%s mengekspor %d data booking ke %s.', $user->name, $rowCount, strtoupper($exportFormat->value)),
            'context' => [
                'format' => $exportFormat->value,
                'row_count' => $rowCount,
                'scope' => $canViewAll ? 'all' : 'own',
                'mode' => $rowCount > $this->syncRowLimit() ? 'queued' : 'sync',
                'filters' => array_filter($filters, static fn (string $value): bool => $value !== ''),
            ],
        ]);

        // Large exports run off the request cycle; the user is notified when ready.
        if ($rowCount > $this->syncRowLimit()) {
            $export = Export::create([
                'user_id' => $user->id,
                'type' => 'bookings',
                'format' => $exportFormat,
                'status' => ExportStatus::Pending,
                'scope' => $canViewAll ? 'all' : 'own',
                'filters' => $filters,
            ]);
            GenerateBookingExportJob::dispatch($export);

            session()->flash('status', sprintf(
                'Ekspor %d data sedang diproses. Anda akan menerima notifikasi saat berkas siap diunduh.',
                $rowCount,
            ));

            return null;
        }

        $bookings = $this->baseQuery($user, $canViewAll)->cursor();
        $filename = 'bookings-export-'.now()->format('Ymd-His').'.'.$exportFormat->extension();

        if ($exportFormat === ExportFormat::Xlsx) {
            $tmp = tempnam(sys_get_temp_dir(), 'export');
            if ($tmp === false) {
                abort(500, 'Tidak dapat membuat berkas sementara.');
            }
            $xlsx->writeToFile($tmp, $bookings, $timezone);

            return response()
                ->download($tmp, $filename, ['Content-Type' => $exportFormat->mimeType()])
                ->deleteFileAfterSend(true);
        }

        return response()->streamDownload(
            function () use ($csv, $bookings, $timezone): void {
                $handle = fopen('php://output', 'w');
                if ($handle === false) {
                    return;
                }
                $csv->writeCsv($handle, $bookings, $timezone);
                fclose($handle);
            },
            $filename,
            ['Content-Type' => $exportFormat->mimeType()],
        );
    }

    /**
     * @return array<string, string>
     */
    private function exportFilters(): array
    {
        return [
            'status' => $this->statusFilter,
            'search' => $this->search,
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
        ];
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
     * Scope + filter logic shared by the list (render) and the export. Delegates
     * to BookingExportQuery so the on-screen list and the (sync/queued) export
     * always apply identical scope and filters.
     *
     * @return Builder<Booking>
     */
    private function baseQuery(User $user, bool $canViewAll): Builder
    {
        return app(BookingExportQuery::class)->build($user, $canViewAll, $this->exportFilters());
    }

    public function displayDateTime(Booking $booking): string
    {
        $tz = $this->resolveTimezone();
        $start = $booking->starts_at->copy()->setTimezone($tz);
        $end = $booking->ends_at->copy()->setTimezone($tz);

        return $start->locale('id')->isoFormat('ddd, D MMM Y').' · '
            .$start->format('H:i').'–'.$end->format('H:i');
    }

    private function syncRowLimit(): int
    {
        return (int) config('exports.sync_row_limit', self::SYNC_EXPORT_LIMIT);
    }

    private function resolveTimezone(): string
    {
        $userTimezone = auth()->check() ? auth()->user()->timezone : null;

        return $userTimezone ?? config('app.display_timezone', self::DISPLAY_TIMEZONE_FALLBACK);
    }
}
