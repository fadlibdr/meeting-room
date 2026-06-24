<?php

declare(strict_types=1);

namespace Tests\Unit\Seeders;

use App\Models\AppSetting;
use App\Services\SettingsService;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_expected_settings(): void
    {
        $this->seed(AppSettingsSeeder::class);

        // 15 base + Stage 3 (max-duration, sso x6, calendar x6) + tenancy flag + telegram x4 + auto-release toggle = 34.
        // + security group: audit (2) + password policy (5) + session timeouts (2) + mfa (3) + calendar-feed (1) = 47.
        $this->assertSame(47, AppSetting::count());
        $this->assertNotNull(AppSetting::where('key', 'sso.enabled')->first());
        $this->assertNotNull(AppSetting::where('key', 'calendar.sync_enabled')->first());
        $this->assertSame('encrypted', AppSetting::where('key', 'sso.azure_client_secret')->value('data_type'));
        $this->assertNotNull(AppSetting::where('key', 'booking.default_buffer_minutes')->first());
        $this->assertNotNull(AppSetting::where('key', 'booking.draft_purge_after_days')->first());
        $this->assertNotNull(AppSetting::where('key', 'notifications.send_email_default')->first());
        $this->assertNotNull(AppSetting::where('key', 'system.maintenance_mode')->first());
        $this->assertNotNull(AppSetting::where('key', 'users.email_domain_restriction')->first());
        $this->assertNotNull(AppSetting::where('key', 'users.email_domain')->first());
        $this->assertNotNull(AppSetting::where('key', 'email.host')->first());
        $this->assertSame('encrypted', AppSetting::where('key', 'email.password')->value('data_type'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(AppSettingsSeeder::class);
        $this->seed(AppSettingsSeeder::class);

        $this->assertSame(47, AppSetting::count());
    }

    public function test_reseeding_preserves_an_admin_edited_value(): void
    {
        $this->seed(AppSettingsSeeder::class);

        app(SettingsService::class)->set('email.host', 'admin-edited.smtp.test');

        // A deploy re-runs the seeder with --force; admin edits must survive.
        $this->seed(AppSettingsSeeder::class);

        $this->assertSame('admin-edited.smtp.test', AppSetting::where('key', 'email.host')->value('value'));
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
