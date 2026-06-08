<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\SeedDemoTenantAction;
use Illuminate\Console\Command;

/**
 * Stage 4h.2 — (re)builds the demo/sandbox tenant with fresh sample data.
 *
 * No-op unless config('demo.enabled') — so it is safe to schedule everywhere;
 * it does nothing in environments where the demo is gated off (the default).
 */
class ResetDemo extends Command
{
    protected $signature = 'demo:reset';

    protected $description = 'Rebuild the demo/sandbox tenant with fresh sample data (no-op unless demo.enabled).';

    public function handle(SeedDemoTenantAction $action): int
    {
        if (! (bool) config('demo.enabled', false)) {
            $this->info('Demo disabled (demo.enabled=false) — nothing to do.');

            return self::SUCCESS;
        }

        $tenant = $action->seed();
        $this->info("Demo tenant #{$tenant->id} ({$tenant->slug}) reset with fresh sample data.");

        return self::SUCCESS;
    }
}
