<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Models\AppSetting;
use App\Providers\MailSettingsServiceProvider;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MailSettingsServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    private function emailSetting(string $key, ?string $value, string $type = 'string'): void
    {
        AppSetting::create([
            'key' => $key,
            'value' => $value,
            'data_type' => $type,
            'label' => $key,
            'group' => 'email',
            'is_editable' => true,
        ]);
    }

    private function reboot(): void
    {
        (new MailSettingsServiceProvider($this->app))->boot();
    }

    public function test_non_empty_settings_override_mail_config(): void
    {
        $this->emailSetting('email.host', 'smtp.example.test');
        $this->emailSetting('email.port', '2525', 'integer');
        $this->emailSetting('email.from_address', 'noreply@example.test');

        $this->reboot();

        $this->assertSame('smtp.example.test', config('mail.mailers.smtp.host'));
        $this->assertSame(2525, config('mail.mailers.smtp.port'));
        $this->assertSame('noreply@example.test', config('mail.from.address'));
    }

    public function test_empty_setting_leaves_env_config_untouched(): void
    {
        config(['mail.mailers.smtp.host' => 'env-host.test']);
        $this->emailSetting('email.host', null);

        $this->reboot();

        $this->assertSame('env-host.test', config('mail.mailers.smtp.host'));
    }

    public function test_encrypted_password_is_decrypted_into_config(): void
    {
        config(['mail.mailers.smtp.password' => 'env-pass']);
        $this->emailSetting('email.password', null, 'encrypted');
        app(SettingsService::class)->set('email.password', 'db-secret');

        $this->reboot();

        $this->assertSame('db-secret', config('mail.mailers.smtp.password'));
    }

    public function test_does_not_crash_when_app_settings_table_is_missing(): void
    {
        config(['mail.mailers.smtp.host' => 'env-host.test']);
        Schema::shouldReceive('hasTable')->with('app_settings')->andReturn(false);

        $this->reboot(); // must not throw

        $this->assertSame('env-host.test', config('mail.mailers.smtp.host'));
    }
}
