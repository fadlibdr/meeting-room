<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;

/**
 * Stage 4a P2c — for console commands / scheduled jobs that scan the whole
 * system. Runs the work once per active tenant (so each tenant's data is
 * processed in isolation) when tenancy is enabled; otherwise runs it once with
 * no context (single-tenant behaviour, unchanged).
 */
trait RunsPerTenant
{
    protected function eachTenant(callable $work): void
    {
        if (! config('tenancy.enabled')) {
            $work();

            return;
        }

        $context = app(TenantContext::class);
        Tenant::query()->where('status', 'active')->orderBy('id')
            ->each(fn (Tenant $tenant) => $context->runFor((int) $tenant->id, $work));
    }
}
