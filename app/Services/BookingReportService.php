<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

/**
 * Stage 3 D — builds period booking reports + the BI feed.
 *
 * Reuses the Stage-2.2 exporters (BookingXlsxExporter / BookingCsvExporter), so
 * the scheduled report, the on-demand export, and the BI feed all share one
 * column layout. Bookings are stored UTC and rendered in the display timezone
 * (Asia/Jakarta) by the exporters. Memory stays flat via a lazy cursor.
 */
final class BookingReportService
{
    public function __construct(
        private readonly BookingXlsxExporter $xlsx,
        private readonly BookingCsvExporter $csv,
    ) {}

    /**
     * Generate an XLSX report for [$from, $to] and store it on the report disk.
     *
     * @return array{path: string, filename: string, rows: int}
     */
    public function generatePeriodXlsx(CarbonImmutable $from, CarbonImmutable $to, string $timezone): array
    {
        $disk = Storage::disk((string) config('reports.report_disk', 'local_private'));
        $dir = (string) config('reports.report_path', 'reports');
        $disk->makeDirectory($dir);

        $relative = $dir.'/booking-report-'.$from->format('Ymd').'-'.$to->format('Ymd').'-'.Str::uuid()->toString().'.xlsx';
        $rows = $this->xlsx->writeToFile($disk->path($relative), $this->bookings($from, $to), $timezone);

        return [
            'path' => $relative,
            'filename' => 'laporan-reservasi-'.$from->format('Ymd').'-'.$to->format('Ymd').'.xlsx',
            'rows' => $rows,
        ];
    }

    /**
     * Write a full bookings snapshot (CSV) to the BI feed disk (push).
     *
     * @return array{path: string, rows: int}
     */
    public function writeBiFeed(string $timezone): array
    {
        $disk = Storage::disk((string) config('reports.bi_disk', 'local_private'));
        $dir = (string) config('reports.bi_path', 'bi-exports');
        $disk->makeDirectory($dir);

        $relative = $dir.'/bookings-latest.csv';
        $absolute = $disk->path($relative);

        $handle = fopen($absolute, 'w');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open BI feed file: {$absolute}");
        }

        $bookings = Booking::query()
            ->with(['room', 'requester', 'requesterUnit'])
            ->orderBy('starts_at')
            ->cursor();

        $this->csv->writeCsv($handle, $bookings, $timezone);
        fclose($handle);

        return ['path' => $relative, 'rows' => Booking::query()->count()];
    }

    /**
     * @return LazyCollection<int, Booking>
     */
    private function bookings(CarbonImmutable $from, CarbonImmutable $to)
    {
        return Booking::query()
            ->whereBetween('starts_at', [$from->utc(), $to->utc()])
            ->with(['room', 'requester', 'requesterUnit'])
            ->orderBy('starts_at')
            ->cursor();
    }
}
