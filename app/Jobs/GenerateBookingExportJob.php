<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\Export;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use App\Services\BookingCsvExporter;
use App\Services\BookingExportQuery;
use App\Services\BookingXlsxExporter;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stage 2.2 — generates a large bookings export off the request cycle.
 *
 * Rebuilds the user's filtered+scoped query (via BookingExportQuery, the same
 * builder the synchronous path uses), streams it to a file on the local_private
 * disk, stamps the Export row complete, and notifies the user. Memory stays flat
 * because both writers consume a lazy cursor.
 */
class GenerateBookingExportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Generated files live for 24h before exports:prune removes them. */
    public const RETENTION_HOURS = 24;

    public int $tries = 3;

    public function __construct(
        private readonly Export $export,
    ) {}

    public function handle(
        BookingExportQuery $queryBuilder,
        BookingXlsxExporter $xlsx,
        BookingCsvExporter $csv,
    ): void {
        $this->process($queryBuilder, $xlsx, $csv);
    }

    private function process(
        BookingExportQuery $queryBuilder,
        BookingXlsxExporter $xlsx,
        BookingCsvExporter $csv,
    ): void {
        $export = $this->export->fresh();
        if ($export === null) {
            return;
        }

        $user = $export->user;
        if (! $user instanceof User) {
            $export->update(['status' => ExportStatus::Failed, 'error' => 'Pengguna tidak ditemukan.']);

            return;
        }

        $export->update(['status' => ExportStatus::Processing]);

        $canViewAll = $export->scope === 'all';
        $timezone = $user->timezone !== null && $user->timezone !== ''
            ? $user->timezone
            : (string) config('app.display_timezone', 'Asia/Jakarta');
        $filters = $export->filters ?? [];

        $rowCount = $queryBuilder->build($user, $canViewAll, $filters)->count();
        $cursor = $queryBuilder->build($user, $canViewAll, $filters)->cursor();

        $disk = Storage::disk(Export::DISK);
        $disk->makeDirectory('exports');
        $relative = 'exports/'.$export->id.'-'.Str::uuid()->toString().'.'.$export->format->extension();
        $absolute = $disk->path($relative);

        if ($export->format === ExportFormat::Xlsx) {
            $xlsx->writeToFile($absolute, $cursor, $timezone);
        } else {
            $handle = fopen($absolute, 'w');
            if ($handle === false) {
                $export->update(['status' => ExportStatus::Failed, 'error' => 'Tidak dapat membuat berkas.']);

                return;
            }
            $csv->writeCsv($handle, $cursor, $timezone);
            fclose($handle);
        }

        $export->update([
            'status' => ExportStatus::Completed,
            'path' => $relative,
            'filename' => 'bookings-export-'.$export->created_at->format('Ymd-His').'.'.$export->format->extension(),
            'row_count' => $rowCount,
            'completed_at' => now(),
            'expires_at' => now()->addHours(self::RETENTION_HOURS),
            'error' => null,
        ]);

        $user->notify(new ExportReadyNotification($export->fresh() ?? $export));
    }

    public function failed(Throwable $exception): void
    {
        $export = $this->export->fresh();
        $export?->update([
            'status' => ExportStatus::Failed,
            'error' => Str::limit($exception->getMessage(), 480),
        ]);
    }
}
