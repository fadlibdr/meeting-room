<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Lets administrators manage SMTP transport from the in-app Settings page
 * (the `email` settings group) instead of the `.env`. At boot it layers any
 * non-empty `email.*` setting on top of the existing `config('mail.*')` values.
 *
 * Resolution per key: DB setting (non-empty) → existing `.env`/config → skip.
 * An empty/null setting NEVER blanks a working credential. If `app_settings`
 * is missing (fresh install / mid-migration) or the DB is down, the `.env`
 * config is left untouched. Runs at request time, so it composes with
 * `config:cache` (it overrides the cached values in memory).
 */
class MailSettingsServiceProvider extends ServiceProvider
{
    /**
     * email.* setting key => the config() dot-path it overrides.
     *
     * @var array<string, string>
     */
    private const MAP = [
        'email.mailer' => 'mail.default',
        'email.host' => 'mail.mailers.smtp.host',
        'email.port' => 'mail.mailers.smtp.port',
        'email.username' => 'mail.mailers.smtp.username',
        'email.password' => 'mail.mailers.smtp.password',
        'email.encryption' => 'mail.mailers.smtp.scheme',
        'email.from_address' => 'mail.from.address',
        'email.from_name' => 'mail.from.name',
    ];

    public function boot(): void
    {
        try {
            if (! Schema::hasTable('app_settings')) {
                return;
            }

            /** @var Collection<string, AppSetting> $settings */
            $settings = AppSetting::query()->where('group', 'email')->get()->keyBy('key');
        } catch (Throwable) {
            return; // DB unavailable — keep the .env mail config as-is.
        }

        foreach (self::MAP as $settingKey => $configPath) {
            $setting = $settings->get($settingKey);
            if ($setting === null) {
                continue;
            }

            $value = $setting->getCastedValue();
            if ($value === null || $value === '') {
                continue; // empty setting must not blank a working .env credential
            }

            config([$configPath => $value]);
        }
    }
}
