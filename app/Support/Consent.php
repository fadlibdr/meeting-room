<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Cookie/consent gate (Stage 4f.4).
 *
 * Reads the plaintext `cookie_consent` cookie (exempted from Laravel's cookie
 * encryption in bootstrap/app.php so the JS-set value is readable server-side).
 *
 * Privacy-preserving default: until the user explicitly opts in, ONLY essential
 * processing is permitted — analytics and other non-essential categories are
 * denied. Gate non-essential scripts with Consent::granted('analytics').
 *
 * Cookie values:
 *   - "all"       → all categories granted (user accepted)
 *   - "essential" → only essential (user declined non-essential)
 *   - absent/other → treated as essential-only (no consent yet)
 */
final class Consent
{
    public const COOKIE = 'cookie_consent';

    /**
     * Has the user made any choice yet? (Drives whether the banner shows.)
     */
    public static function decided(): bool
    {
        return in_array(self::value(), ['all', 'essential'], true);
    }

    public static function granted(string $category): bool
    {
        // Essential processing is always permitted (sessions, security, CSRF).
        if ($category === 'essential') {
            return true;
        }

        // Everything else requires an explicit "accept all".
        return self::value() === 'all';
    }

    private static function value(): string
    {
        $raw = request()->cookie(self::COOKIE);

        return is_string($raw) ? $raw : '';
    }
}
