<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\AppSetting;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class EncryptedSettingTest extends TestCase
{
    use RefreshDatabase;

    private function encryptedSetting(?string $value = null): AppSetting
    {
        return AppSetting::create([
            'key' => 'email.password',
            'value' => $value,
            'data_type' => 'encrypted',
            'label' => 'SMTP Password',
            'group' => 'email',
            'is_editable' => true,
        ]);
    }

    public function test_set_stores_ciphertext_and_get_returns_plaintext(): void
    {
        $this->encryptedSetting();
        $service = app(SettingsService::class);

        $service->set('email.password', 'sup3r-secret');

        $raw = AppSetting::query()->where('key', 'email.password')->value('value');
        $this->assertNotNull($raw);
        $this->assertNotSame('sup3r-secret', $raw, 'The DB column must hold ciphertext, not the plaintext.');

        $this->assertSame('sup3r-secret', $service->get('email.password'));
    }

    public function test_get_casted_value_decrypts(): void
    {
        $setting = $this->encryptedSetting();
        app(SettingsService::class)->set('email.password', 'pa55');

        $this->assertSame('pa55', $setting->fresh()?->getCastedValue());
    }

    public function test_decrypt_safely_returns_null_on_garbage(): void
    {
        $setting = $this->encryptedSetting('this-is-not-valid-ciphertext');

        $this->assertNull($setting->getCastedValue());
    }

    public function test_encrypted_value_is_not_cached_in_plaintext(): void
    {
        $this->encryptedSetting();
        $service = app(SettingsService::class);
        $service->set('email.password', 'secret');

        $service->get('email.password'); // would populate cache for non-encrypted types

        $this->assertNull(Cache::get('app_settings.email.password'));
    }
}
