<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\RuntimeSettingsServiceProvider;
use App\Services\SettingsService;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RuntimeSettingsServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    private function reboot(): void
    {
        (new RuntimeSettingsServiceProvider($this->app))->boot();
    }

    public function test_tenancy_enabled_toggle_drives_config_and_is_read_unscoped(): void
    {
        $this->seed(AppSettingsSeeder::class);
        $service = app(SettingsService::class);

        config(['tenancy.enabled' => false]);
        $service->set('system.tenancy_enabled', true);
        $this->reboot();
        $this->assertTrue(config('tenancy.enabled'));

        // Even with a tenant context set, the platform flag resolves (unscoped).
        app(TenantContext::class)->set(999);
        config(['tenancy.enabled' => false]);
        $this->reboot();
        $this->assertTrue(config('tenancy.enabled'));
    }

    public function test_boolean_toggle_overrides_config_both_ways(): void
    {
        $this->seed(AppSettingsSeeder::class);
        $service = app(SettingsService::class);

        config(['sso.enabled' => false]);
        $service->set('sso.enabled', true);
        $this->reboot();
        $this->assertTrue(config('sso.enabled'));

        config(['sso.enabled' => true]);
        $service->set('sso.enabled', false);
        $this->reboot();
        $this->assertFalse(config('sso.enabled')); // false is a meaningful toggle-off
    }

    public function test_secret_overrides_config_when_set_but_blank_keeps_env(): void
    {
        $this->seed(AppSettingsSeeder::class);
        $service = app(SettingsService::class);

        // Blank secret → keep the existing .env/config value.
        config(['services.azure.client_secret' => 'from-env']);
        $this->reboot();
        $this->assertSame('from-env', config('services.azure.client_secret'));

        // Set secret → overrides, and feeds the calendar.microsoft path too.
        $service->set('sso.azure_client_secret', 'super-secret');
        config(['services.azure.client_secret' => 'from-env', 'calendar.microsoft.client_secret' => 'from-env']);
        $this->reboot();
        $this->assertSame('super-secret', config('services.azure.client_secret'));
        $this->assertSame('super-secret', config('calendar.microsoft.client_secret'));
    }

    public function test_integer_setting_overrides_max_duration(): void
    {
        $this->seed(AppSettingsSeeder::class);
        app(SettingsService::class)->set('system.max_booking_duration_hours', 4);

        config(['meeting_room.max_booking_duration_hours' => 8]);
        $this->reboot();

        $this->assertSame(4, (int) config('meeting_room.max_booking_duration_hours'));
    }

    public function test_does_not_crash_when_app_settings_table_is_missing(): void
    {
        Schema::shouldReceive('hasTable')->with('app_settings')->andReturn(false);

        config(['sso.enabled' => true]);
        $this->reboot();

        $this->assertTrue(config('sso.enabled')); // untouched
    }
}
