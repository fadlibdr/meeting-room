<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Services\SettingsService;
use App\Support\PasswordPolicy;
use Database\Seeders\AppSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Release B — configurable password policy (SOC 2 CC6.1 / ISO 27001 A.5.17).
 */
class PasswordPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AppSettingsSeeder::class);
    }

    private function fails(string $password): bool
    {
        return Validator::make(['p' => $password], ['p' => [PasswordPolicy::rule()]])->fails();
    }

    public function test_default_policy_enforces_length_case_and_numbers(): void
    {
        $this->assertTrue($this->fails('Short1A'), 'too short (<12)');
        $this->assertTrue($this->fails('alllowercase123'), 'no uppercase');
        $this->assertTrue($this->fails('NoNumbersHereAtAll'), 'no digit');
        $this->assertFalse($this->fails('ValidPassword123'), 'meets default policy');
    }

    public function test_min_length_is_configurable(): void
    {
        app(SettingsService::class)->set('security.password_min_length', '20');

        $this->assertTrue($this->fails('ValidPassword123'), '16 chars now too short for min 20');
        $this->assertFalse($this->fails('ValidPassword1234567'.'8'), '20 chars passes');
    }

    public function test_symbols_can_be_required(): void
    {
        app(SettingsService::class)->set('security.password_require_symbols', '1');

        $this->assertTrue($this->fails('ValidPassword123'), 'no symbol');
        $this->assertFalse($this->fails('ValidPassword123!'), 'has symbol');
    }

    public function test_relaxing_the_policy_takes_effect(): void
    {
        $settings = app(SettingsService::class);
        $settings->set('security.password_min_length', '6');
        $settings->set('security.password_require_mixed_case', '0');
        $settings->set('security.password_require_numbers', '0');

        $this->assertFalse($this->fails('simple'), 'relaxed policy accepts a simple 6-char password');
    }
}
