<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Http\Middleware\ResolveTenant;
use App\Models\Tenant;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class TenantResolutionTest extends TestCase
{
    use RefreshDatabase;

    private function resolveHost(string $host): ?int
    {
        app(TenantContext::class)->forget();
        $request = Request::create('http://'.$host.'/dashboard');
        (new ResolveTenant)->handle($request, fn ($r) => response('ok'));

        return app(TenantContext::class)->id();
    }

    public function test_resolves_by_primary_domain(): void
    {
        config(['tenancy.enabled' => true]);
        $acme = Tenant::factory()->create(['primary_domain' => 'acme.example', 'slug' => 'acme']);

        $this->assertSame($acme->id, $this->resolveHost('acme.example'));
    }

    public function test_resolves_by_subdomain_slug(): void
    {
        config(['tenancy.enabled' => true]);
        $acme = Tenant::factory()->create(['slug' => 'acme', 'primary_domain' => null]);

        $this->assertSame($acme->id, $this->resolveHost('acme.app.example'));
    }

    public function test_unknown_host_falls_back_to_default_tenant(): void
    {
        config(['tenancy.enabled' => true]);
        $defaultId = Tenant::where('is_default', true)->value('id');

        $this->assertSame($defaultId, $this->resolveHost('something-unknown.example'));
    }

    public function test_no_op_when_tenancy_disabled(): void
    {
        config(['tenancy.enabled' => false]);
        Tenant::factory()->create(['primary_domain' => 'acme.example']);

        $this->assertNull($this->resolveHost('acme.example'));
    }

    public function test_run_for_sets_and_restores_context(): void
    {
        $ctx = app(TenantContext::class);
        $ctx->set(7);

        $inside = $ctx->runFor(42, fn () => $ctx->id());

        $this->assertSame(42, $inside);
        $this->assertSame(7, $ctx->id()); // restored
    }
}
