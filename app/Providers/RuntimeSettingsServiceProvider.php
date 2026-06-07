<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Lets administrators manage SSO, calendar-sync and booking parameters from the
 * in-app Settings page instead of `.env`. At boot it layers the `sso.*`,
 * `calendar.*` and `system.*` settings on top of the matching `config()` paths.
 *
 * Resolution mirrors MailSettingsServiceProvider:
 *  - boolean settings ALWAYS apply (false is a meaningful value — a toggle off),
 *  - string/integer/encrypted settings apply only when non-empty, so a blank
 *    field never blanks a working `.env` credential,
 *  - if `app_settings` is missing or the DB is down, `.env` is left untouched.
 */
class RuntimeSettingsServiceProvider extends ServiceProvider
{
    /**
     * setting key => config() dot-paths it overrides (one setting may feed many).
     *
     * @var array<string, list<string>>
     */
    private const MAP = [
        'system.max_booking_duration_hours' => ['meeting_room.max_booking_duration_hours'],

        'sso.enabled' => ['sso.enabled'],
        'sso.auto_provision' => ['sso.auto_provision'],
        'sso.default_role' => ['sso.default_role'],
        'sso.azure_tenant_id' => ['services.azure.tenant', 'calendar.microsoft.tenant'],
        'sso.azure_client_id' => ['services.azure.client_id', 'calendar.microsoft.client_id'],
        'sso.azure_client_secret' => ['services.azure.client_secret', 'calendar.microsoft.client_secret'],

        'calendar.sync_enabled' => ['calendar.sync.enabled'],
        'calendar.consent_mode' => ['calendar.sync.consent_mode'],
        'calendar.microsoft_enabled' => ['calendar.microsoft.enabled'],
        'calendar.google_enabled' => ['calendar.google.enabled'],
        'calendar.google_client_id' => ['calendar.google.client_id'],
        'calendar.google_client_secret' => ['calendar.google.client_secret'],
    ];

    public function boot(): void
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return;
            }

            // Platform flag FIRST, read UNSCOPED — it gates tenancy itself, so it
            // must not be tenant-scoped. Stored on the platform (default) tenant.
            $tenancyFlag = AppSetting::query()->withoutGlobalScope('tenant')
                ->where('key', 'system.tenancy_enabled')->first();
            if ($tenancyFlag !== null) {
                config(['tenancy.enabled' => (bool) $tenancyFlag->getCastedValue()]);
            }

            /** @var Collection<string, AppSetting> $settings */
            $settings = AppSetting::query()
                ->whereIn('group', ['sso', 'calendar', 'system'])
                ->get()
                ->keyBy('key');
        } catch (Throwable) {
            return; // DB unavailable — keep the .env config as-is.
        }

        foreach (self::MAP as $settingKey => $configPaths) {
            $setting = $settings->get($settingKey);
            if ($setting === null) {
                continue;
            }

            $value = $setting->getCastedValue();

            // Non-boolean: an empty value must not blank a working .env value.
            if ($setting->data_type !== 'boolean' && ($value === null || $value === '')) {
                continue;
            }

            foreach ($configPaths as $configPath) {
                config([$configPath => $value]);
            }
        }
    }
}
