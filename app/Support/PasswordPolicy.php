<?php

declare(strict_types=1);

namespace App\Support;

use App\Services\SettingsService;
use Illuminate\Validation\Rules\Password;

/**
 * Builds the application password policy (SOC 2 CC6.1 / ISO 27001 A.5.17) from
 * the operator-configurable `security.password_*` settings. Registered as
 * Laravel's default password rule in AppServiceProvider, so every
 * `Password::defaults()` call site (force-change, reset, profile update,
 * registration) enforces the same configured policy.
 */
final class PasswordPolicy
{
    /**
     * Maximum length — bcrypt only hashes the first 72 bytes, so cap input there.
     */
    public const MAX_LENGTH = 72;

    public static function rule(): Password
    {
        $settings = app(SettingsService::class);

        $min = max(1, (int) $settings->get('security.password_min_length', 12));
        $rule = Password::min($min);

        if ((bool) $settings->get('security.password_require_mixed_case', true)) {
            $rule->mixedCase();
        }

        if ((bool) $settings->get('security.password_require_numbers', true)) {
            $rule->numbers();
        }

        if ((bool) $settings->get('security.password_require_symbols', false)) {
            $rule->symbols();
        }

        // HaveIBeenPwned k-anonymity check makes an outbound HTTPS call, so skip it
        // under the test runner (deterministic, offline) while enforcing in prod.
        if ((bool) $settings->get('security.password_check_breached', true) && ! app()->runningUnitTests()) {
            $rule->uncompromised();
        }

        return $rule;
    }
}
