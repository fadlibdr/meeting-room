<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\SystemHealthNotification;
use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Self-contained system health check (Stage 1 monitoring). Verifies DB, queue
 * worker liveness, failed-job backlog, free disk, and mail config; logs and
 * exits non-zero on any failure (so a cron/external monitor catches it) and
 * alerts admins — throttled so a sustained outage doesn't spam.
 *
 * Scheduled every 5 minutes (routes/console.php).
 */
class SystemHealthCheck extends Command
{
    protected $signature = 'system:health-check';

    protected $description = 'Memeriksa kesehatan sistem (DB, antrean, disk, email) dan memberi tahu admin bila ada masalah.';

    /** Stale-job threshold: a pending job older than this means the worker is likely dead. */
    private const STALE_JOB_MINUTES = 5;

    /** Free-disk floor before alerting. */
    private const MIN_FREE_DISK_PERCENT = 15.0;

    /** Don't re-alert more than once per this window. */
    private const ALERT_THROTTLE_MINUTES = 60;

    private const THROTTLE_KEY = 'system_health.alert_sent';

    public function handle(): int
    {
        $issues = array_merge(
            $this->checkDatabase(),
            $this->checkQueueWorker(),
            $this->checkFailedJobs(),
            $this->checkDisk(),
            $this->checkMailConfig(),
        );

        if ($issues === []) {
            $this->info('Sistem sehat.');

            return self::SUCCESS;
        }

        Log::warning('system.health_check.failed', ['issues' => $issues]);
        foreach ($issues as $issue) {
            $this->error($issue);
        }

        if (! Cache::has(self::THROTTLE_KEY)) {
            $this->alertAdmins($issues);
            Cache::put(self::THROTTLE_KEY, true, now()->addMinutes(self::ALERT_THROTTLE_MINUTES));
        }

        return self::FAILURE;
    }

    /**
     * @return array<int, string>
     */
    private function checkDatabase(): array
    {
        try {
            DB::connection()->getPdo();

            return [];
        } catch (Throwable $e) {
            return ['Koneksi basis data gagal: '.$e->getMessage()];
        }
    }

    /**
     * @return array<int, string>
     */
    private function checkQueueWorker(): array
    {
        try {
            $oldest = DB::table('jobs')->min('created_at');
        } catch (Throwable) {
            return []; // jobs table absent (sync env) — nothing to check
        }

        if ($oldest === null) {
            return []; // queue empty
        }

        $threshold = now()->subMinutes(self::STALE_JOB_MINUTES)->getTimestamp();
        if ((int) $oldest < $threshold) {
            return ['Antrean macet: job tertua menunggu lebih dari '.self::STALE_JOB_MINUTES.' menit — worker kemungkinan mati.'];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function checkFailedJobs(): array
    {
        try {
            $count = DB::table('failed_jobs')->count();
        } catch (Throwable) {
            return [];
        }

        return $count > 0 ? ["Terdapat {$count} job gagal pada antrean (failed_jobs)."] : [];
    }

    /**
     * @return array<int, string>
     */
    private function checkDisk(): array
    {
        $path = storage_path();
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0.0) {
            return [];
        }

        $percentFree = $free / $total * 100;
        if ($percentFree < self::MIN_FREE_DISK_PERCENT) {
            return [sprintf('Ruang disk menipis: %.1f%% tersisa (ambang %.0f%%).', $percentFree, self::MIN_FREE_DISK_PERCENT)];
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    private function checkMailConfig(): array
    {
        $emailOn = (bool) app(SettingsService::class)->get('notifications.send_email_default', false);

        if ($emailOn && config('mail.default') === 'log') {
            return ['Notifikasi email diaktifkan, tetapi mailer masih "log" — email tidak akan terkirim. Konfigurasikan SMTP di Pengaturan → Email.'];
        }

        return [];
    }

    /**
     * @param  array<int, string>  $issues
     */
    private function alertAdmins(array $issues): void
    {
        $admins = User::query()
            ->where('is_active', true)
            ->whereHas('roles.permissions', fn ($q) => $q->where('code', 'app-settings.update'))
            ->get();

        if ($admins->isNotEmpty()) {
            Notification::sendNow($admins, new SystemHealthNotification($issues));
        }
    }
}
