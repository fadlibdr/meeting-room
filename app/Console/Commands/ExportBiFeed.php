<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BookingReportService;
use Illuminate\Console\Command;

/**
 * Stage 3 D — BI feed (push).
 *
 * Writes a full bookings snapshot (CSV, Jakarta-labelled times) to the
 * configured BI disk/path for a BI tool to pick up. Scheduled daily in
 * routes/console.php.
 */
class ExportBiFeed extends Command
{
    protected $signature = 'reports:bi-export';

    protected $description = 'Write a full bookings snapshot (CSV) to the BI feed path.';

    public function handle(BookingReportService $reports): int
    {
        // Scopes the snapshot per tenant. NOTE (P3 follow-up): the BI feed path
        // must become per-tenant before multi-tenant go-live, else tenants
        // overwrite one file — tracked in the tenancy rollout.
        $tz = (string) config('app.display_timezone', 'Asia/Jakarta');
        $result = $reports->writeBiFeed($tz);
        $this->info("BI feed written: {$result['path']} ({$result['rows']} row(s)).");

        return self::SUCCESS;
    }
}
