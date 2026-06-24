<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Export;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Deletes expired export files and their rows. Generated exports are retained
 * for GenerateBookingExportJob::RETENTION_HOURS, after which both the file on
 * the local_private disk and the database row are removed.
 *
 * Scheduled hourly in routes/console.php.
 */
class PruneExports extends Command
{
    protected $signature = 'exports:prune';

    protected $description = 'Delete expired export files and records.';

    public function handle(): int
    {
        $this->pruneForCurrentTenant();

        return self::SUCCESS;
    }

    private function pruneForCurrentTenant(): void
    {
        $disk = Storage::disk(Export::DISK);
        $pruned = 0;

        Export::query()->expired()->each(function (Export $export) use ($disk, &$pruned): void {
            if ($export->path !== null && $disk->exists($export->path)) {
                $disk->delete($export->path);
            }
            $export->delete();
            $pruned++;
        });

        $this->info("Pruned {$pruned} expired export(s).");
    }
}
