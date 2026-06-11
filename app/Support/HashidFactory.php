<?php

declare(strict_types=1);

namespace App\Support;

use Hashids\Hashids;

/**
 * Builds and caches per-context {@see Hashids} encoders.
 *
 * Public URLs expose obfuscated, non-sequential identifiers instead of raw
 * auto-increment primary keys, so resources can't be enumerated or guessed
 * (e.g. /bookings/3 → /bookings/4). Each model gets its own salt, so the same
 * integer encodes differently per model and a hashid minted for one model can
 * never decode against another.
 *
 * The salt is derived from the application key (APP_KEY): it never leaves the
 * server, and rotating the key invalidates every previously issued hashid.
 * This is obfuscation, not encryption — it raises the cost of enumeration but
 * authorization is still enforced by policies/middleware on every route.
 */
final class HashidFactory
{
    /** @var array<string, Hashids> */
    private static array $cache = [];

    /**
     * A minimum length keeps short ids (1, 2, …) from producing tell-tale
     * short hashes that leak the magnitude of the underlying key.
     */
    private const MIN_LENGTH = 12;

    /**
     * URL-safe alphabet (no look-alike punctuation, no padding chars).
     */
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';

    public static function for(string $context): Hashids
    {
        return self::$cache[$context] ??= new Hashids(
            self::salt($context),
            self::MIN_LENGTH,
            self::ALPHABET,
        );
    }

    public static function encode(string $context, int $id): string
    {
        return self::for($context)->encode($id);
    }

    /**
     * Decode a hashid back to its integer id, or null if the value is not a
     * valid hashid for this context (wrong format, tampered, or minted for a
     * different model). Callers treat null as "not found" → 404.
     */
    public static function decode(string $context, string $hash): ?int
    {
        $decoded = self::for($context)->decode($hash);

        if ($decoded === []) {
            return null;
        }

        return (int) $decoded[0];
    }

    private static function salt(string $context): string
    {
        // The per-context discriminator must lead: Hashids' alphabet shuffle is
        // insensitive to salt differences buried after a long common prefix, so
        // putting the (long) app key first would collapse every model to the
        // same encoding. Context first, secret key second.
        return $context.'|hashid|'.(string) config('app.key');
    }

    /**
     * Reset the instance cache — used in tests that swap the app key.
     */
    public static function flush(): void
    {
        self::$cache = [];
    }
}
