<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Stage 4g.3 — computes a SUMMARISED system status for the public status page.
 *
 * Deliberately shallow on the public surface: callers get only an overall
 * severity (up / degraded / down). Internal component detail is NOT exposed
 * publicly — consistent with the shallow /up decision; the depth stays in the
 * admin-facing `system:health-check` command (which alerts with specifics).
 */
class SystemHealthService
{
    public const UP = 'up';

    public const DEGRADED = 'degraded';

    public const DOWN = 'down';

    /** Stale-job threshold: a pending job older than this suggests a dead worker. */
    private const STALE_JOB_MINUTES = 5;

    /** Free-disk floor before we call the system degraded. */
    private const MIN_FREE_DISK_PERCENT = 15.0;

    /**
     * The overall, public-safe status string only. No component breakdown.
     */
    public function status(): string
    {
        // DB unreachable = core service down.
        if (! $this->databaseUp()) {
            return self::DOWN;
        }

        // Anything else wrong = degraded (still serving, but not fully healthy).
        if ($this->queueStale() || $this->hasFailedJobs() || $this->diskLow()) {
            return self::DEGRADED;
        }

        return self::UP;
    }

    private function databaseUp(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function queueStale(): bool
    {
        try {
            $oldest = DB::table('jobs')->min('created_at');
        } catch (Throwable) {
            return false;
        }

        if ($oldest === null) {
            return false;
        }

        return (int) $oldest < now()->subMinutes(self::STALE_JOB_MINUTES)->getTimestamp();
    }

    private function hasFailedJobs(): bool
    {
        try {
            return DB::table('failed_jobs')->count() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function diskLow(): bool
    {
        $free = @disk_free_space(storage_path());
        $total = @disk_total_space(storage_path());

        if ($free === false || $total === false || $total <= 0.0) {
            return false;
        }

        return ($free / $total * 100) < self::MIN_FREE_DISK_PERCENT;
    }
}
