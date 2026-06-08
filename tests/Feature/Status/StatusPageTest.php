<?php

declare(strict_types=1);

namespace Tests\Feature\Status;

use App\Services\SystemHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::forget('public_status.summary');
    }

    public function test_status_page_is_public_and_shows_operational_when_healthy(): void
    {
        $this->get(route('status'))
            ->assertOk()
            ->assertSee('Operasional', false);
    }

    public function test_status_page_never_leaks_internal_component_detail(): void
    {
        // Even when degraded, the public page must not name components/errors.
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'secret stack trace',
            'failed_at' => now(),
        ]);
        Cache::forget('public_status.summary');

        $res = $this->get(route('status'))->assertOk();
        $res->assertDontSee('failed_jobs', false);
        $res->assertDontSee('secret stack trace', false);
        $res->assertDontSee('disk', false);
    }

    public function test_service_reports_up_on_a_clean_system(): void
    {
        $this->assertSame(SystemHealthService::UP, app(SystemHealthService::class)->status());
    }

    public function test_service_reports_degraded_with_failed_jobs(): void
    {
        DB::table('failed_jobs')->insert([
            'uuid' => (string) Str::uuid(),
            'connection' => 'database',
            'queue' => 'default',
            'payload' => '{}',
            'exception' => 'x',
            'failed_at' => now(),
        ]);

        $this->assertSame(SystemHealthService::DEGRADED, app(SystemHealthService::class)->status());
    }
}
