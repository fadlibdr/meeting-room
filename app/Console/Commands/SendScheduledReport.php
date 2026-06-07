<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\ScheduledReportNotification;
use App\Services\BookingReportService;
use App\Services\RoomUtilizationReport;
use App\Support\Tenancy\RunsPerTenant;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Stage 3 D — emails a period booking/utilization report to admins.
 *
 * `reports:send --period=weekly|monthly`. Builds the XLSX for the PREVIOUS
 * complete week/month (display timezone), computes the utilization summary, and
 * queues a mail to every active holder of the configured recipient permission.
 * Scheduled in routes/console.php.
 */
class SendScheduledReport extends Command
{
    use RunsPerTenant;

    protected $signature = 'reports:send {--period=weekly : weekly|monthly}';

    protected $description = 'Email the periodic booking/utilization report to report viewers.';

    public function handle(BookingReportService $reports): int
    {
        $this->eachTenant(fn () => $this->sendForCurrentTenant($reports));

        return self::SUCCESS;
    }

    private function sendForCurrentTenant(BookingReportService $reports): void
    {
        $period = $this->option('period') === 'monthly' ? 'monthly' : 'weekly';
        $tz = (string) config('app.display_timezone', 'Asia/Jakarta');

        [$from, $to, $label] = $this->range($period, $tz);

        $report = $reports->generatePeriodXlsx($from, $to, $tz);
        $summary = (new RoomUtilizationReport($tz))->build($from, $to)['summary'];

        $recipients = User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($q) => $q->where('code', (string) config('reports.recipient_permission', 'reports.view')))
            ->get();

        if ($recipients->isEmpty()) {
            $this->warn('No recipients with the report permission; nothing sent.');

            return;
        }

        Notification::send($recipients, new ScheduledReportNotification(
            $label,
            $from->format('d/m/Y'),
            $to->format('d/m/Y'),
            $summary,
            $report['path'],
            $report['filename'],
        ));

        $this->info("Queued {$period} report ({$report['rows']} rows) to {$recipients->count()} recipient(s).");
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: string}
     */
    private function range(string $period, string $tz): array
    {
        $now = CarbonImmutable::now($tz);

        if ($period === 'monthly') {
            $from = $now->subMonthNoOverflow()->startOfMonth();

            return [$from, $from->endOfMonth(), 'Bulanan'];
        }

        $from = $now->subWeek()->startOfWeek();

        return [$from, $from->endOfWeek(), 'Mingguan'];
    }
}
