<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\AnonymizeUserAction;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\SettingsService;
use App\Support\Tenancy\RunsPerTenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Stage 4f.3 — data retention enforcement (UU PDP).
 *
 * Anonymises personal data that has aged past its configured retention window
 * (config/retention.php). DRY-RUN by default; --execute to act. Anonymisation
 * (not hard-delete) preserves audit/booking integrity — the row stays, the
 * person becomes unidentifiable.
 *
 * Safety, by design:
 *   - dry-run is the default; scheduling the bare command never deletes data.
 *   - a bounded-window guard refuses to act on a category with more eligible
 *     records than retention.max_per_run unless --force-bulk is given (mirrors
 *     the auto-release "don't retroactively mass-act on first run" lesson).
 *   - actions are audit-logged (per-user by AnonymizeUserAction + a run summary).
 *   - idempotent: already-anonymised users are excluded from eligibility.
 *
 * Runs per active tenant when tenancy is enabled; once globally otherwise.
 */
class EnforceDataRetention extends Command
{
    use RunsPerTenant;

    protected $signature = 'data:enforce-retention
                            {--execute : Actually anonymise. Without this the command only reports (dry-run).}
                            {--force-bulk : Bypass the bounded-window guard and act even on large eligible sets.}';

    protected $description = 'Anonymise personal data past its retention window (UU PDP). Dry-run unless --execute.';

    private const ANON_EMAIL_SUFFIX = '@anonymized.invalid';

    public function handle(AnonymizeUserAction $anonymizer): int
    {
        $execute = (bool) $this->option('execute');
        $forceBulk = (bool) $this->option('force-bulk');

        $this->line($execute ? '<comment>EXECUTE mode — data will be anonymised.</comment>' : '<info>DRY-RUN — no data will be changed. Pass --execute to act.</info>');

        $this->eachTenant(function () use ($anonymizer, $execute, $forceBulk): void {
            $this->enforceInactiveUsers($anonymizer, $execute, $forceBulk);
            $this->enforceAuditLogRetention($execute);
        });

        return self::SUCCESS;
    }

    /**
     * Prune security/audit logs older than security.audit_log_retention_days
     * (SOC 2 CC7.3 retention). Dry-run unless --execute, like the rest of this
     * command. Pruning is the only sanctioned deletion path for append-only logs.
     */
    private function enforceAuditLogRetention(bool $execute): void
    {
        $days = max(1, (int) app(SettingsService::class)->get('security.audit_log_retention_days', 365));
        $cutoff = now()->subDays($days);

        $count = ActivityLog::query()->where('created_at', '<', $cutoff)->count();

        if ($count === 0) {
            $this->line("  audit_logs: 0 record(s) past {$days}d window.");

            return;
        }

        if (! $execute) {
            $this->line("  audit_logs: {$count} record(s) WOULD be pruned (past {$days}d window).");

            return;
        }

        $pruned = ActivityLog::pruneOlderThan($cutoff);
        $this->line("  audit_logs: pruned {$pruned} record(s) past {$days}d window.");
    }

    private function enforceInactiveUsers(AnonymizeUserAction $anonymizer, bool $execute, bool $forceBulk): void
    {
        $category = (array) config('retention.categories.inactive_users', []);
        if (! ($category['enabled'] ?? false)) {
            $this->line('  inactive_users: disabled — skipped.');

            return;
        }

        $days = max(1, (int) ($category['days'] ?? 1095));
        $cutoff = now()->subDays($days);

        $eligible = $this->eligibleInactiveUsers($cutoff);
        $count = $eligible->count();

        if ($count === 0) {
            $this->line("  inactive_users: 0 record(s) past {$days}d window.");

            return;
        }

        $maxPerRun = max(0, (int) config('retention.max_per_run', 50));
        if ($execute && ! $forceBulk && $count > $maxPerRun) {
            $this->warn("  inactive_users: {$count} eligible exceeds max_per_run ({$maxPerRun}). REFUSING to act — re-run with --force-bulk if this is intended.");
            $this->logRun('inactive_users', $count, 0, 'guard_blocked', $days);

            return;
        }

        if (! $execute) {
            $this->line("  inactive_users: {$count} record(s) WOULD be anonymised (past {$days}d window).");

            return;
        }

        $done = 0;
        foreach ($eligible->get() as $user) {
            if (! $user instanceof User) {
                continue;
            }
            $anonymizer->execute($user, null); // actorId null = system/scheduled
            $done++;
        }

        $this->info("  inactive_users: anonymised {$done} record(s).");
        $this->logRun('inactive_users', $count, $done, 'executed', $days);
    }

    /**
     * Deactivated users whose record last changed before the cutoff and who are
     * not already anonymised. is_active=false is the "left the organisation"
     * signal; the anonymised-email exclusion makes the command idempotent.
     */
    private function eligibleInactiveUsers(Carbon $cutoff): Builder
    {
        return User::query()
            ->where('is_active', false)
            ->where('updated_at', '<', $cutoff)
            ->where('email', 'not like', '%'.self::ANON_EMAIL_SUFFIX);
    }

    private function logRun(string $category, int $eligible, int $acted, string $outcome, int $days): void
    {
        ActivityLog::create([
            'actor_user_id' => null,
            'module' => 'retention',
            'event' => 'enforce',
            'subject_type' => null,
            'subject_id' => null,
            'description' => sprintf('Penegakan retensi (%s): %d memenuhi syarat, %d diproses [%s].', $category, $eligible, $acted, $outcome),
            'context' => [
                'category' => $category,
                'window_days' => $days,
                'eligible' => $eligible,
                'acted' => $acted,
                'outcome' => $outcome,
            ],
        ]);
    }
}
