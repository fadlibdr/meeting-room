<?php

declare(strict_types=1);

namespace Tests\Unit\Seeders;

use App\Models\AppSetting;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $this->assertSame(4, AppSetting::count());
        $this->assertNotNull(AppSetting::where('key', 'booking.default_buffer_minutes')->first());
        $this->assertNotNull(AppSetting::where('key', 'booking.draft_purge_after_days')->first());
        $this->assertNotNull(AppSetting::where('key', 'notifications.send_email_default')->first());
        $this->assertNotNull(AppSetting::where('key', 'system.maintenance_mode')->first());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(AppSettingsSeeder::class);
        $this->seed(AppSettingsSeeder::class);

        $this->assertSame(4, AppSetting::count());
    }

    public function test_default_buffer_minutes_value_is_15(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $setting = AppSetting::where('key', 'booking.default_buffer_minutes')->first();
        $this->assertSame(15, $setting->getCastedValue());
    }

    public function test_maintenance_mode_default_is_false(): void
    {
        $this->seed(AppSettingsSeeder::class);

        $setting = AppSetting::where('key', 'system.maintenance_mode')->first();
        $this->assertFalse($setting->getCastedValue());
    }
}
