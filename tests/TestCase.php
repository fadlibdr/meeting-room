<?php

namespace Tests;

use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    /**
     * Stage 4a closeout — when the suite runs with TENANCY_ENABLED=true, place
     * every test inside the default tenant's context (the prod single-tenant
     * shape) so all global scopes are active. This is the flag-on regression
     * gate: the whole app must work under live tenancy. Tenancy-specific tests
     * override the context as needed. No-op when tenancy is off.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (! config('tenancy.enabled')) {
            return;
        }

        try {
            if (! Schema::hasTable('tenants')) {
                return;
            }
            $defaultId = Tenant::where('is_default', true)->value('id');
            if ($defaultId !== null) {
                app(TenantContext::class)->set((int) $defaultId);
            }
        } catch (Throwable) {
            // No DB (pure unit test) — nothing to scope.
        }
    }
}
