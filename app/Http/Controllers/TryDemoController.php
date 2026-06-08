<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\SeedDemoTenantAction;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Stage 4h.2 — the "try it" entry point. HARD-GATED: 404s unless
 * config('demo.enabled'). When enabled it ensures the demo tenant is seeded,
 * pins the tenant context, and logs the visitor into the shared demo user,
 * then drops them on the dashboard.
 *
 * NOTE: a real launch requires the demo to be served from its own host so
 * ResolveTenant pins the demo tenant for the whole session — see config/demo.php.
 */
class TryDemoController extends Controller
{
    public function __invoke(SeedDemoTenantAction $action): RedirectResponse
    {
        abort_unless((bool) config('demo.enabled', false), 404);

        $tenant = $action->seed();
        app(TenantContext::class)->set($tenant->id);

        $user = $action->demoUser();
        abort_unless($user instanceof User, 404);

        Auth::login($user);

        return redirect()->route('dashboard');
    }
}
