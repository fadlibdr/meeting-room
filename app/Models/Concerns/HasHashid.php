<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Support\HashidFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Exposes an obfuscated, non-sequential hashid as the model's route key so
 * public URLs never reveal the sequential primary key (mitigating resource
 * enumeration and id-guessing). The raw integer key is unchanged in storage —
 * only the URL representation is masked.
 *
 * Usage:
 *  - `route('bookings.show', $booking)` automatically emits the hashid
 *    (Laravel calls {@see getRouteKey()}).
 *  - `$booking->hashid` for manual URL building.
 *  - Implicit + scoped route-model binding decode the hashid transparently
 *    (both funnel through {@see resolveRouteBindingQuery()}).
 *  - For routes that take a raw id param instead of a bound model, decode with
 *    `Model::decodeHashid($value)` / `Model::decodeHashidOrFail($value)`.
 *
 * @phpstan-require-extends Model
 */
trait HasHashid
{
    /**
     * Context string that salts this model's hashids (defaults to the class).
     */
    public static function hashidContext(): string
    {
        return static::class;
    }

    public static function encodeHashid(int $id): string
    {
        return HashidFactory::encode(static::hashidContext(), $id);
    }

    public static function decodeHashid(?string $hash): ?int
    {
        if ($hash === null || $hash === '') {
            return null;
        }

        return HashidFactory::decode(static::hashidContext(), $hash);
    }

    /**
     * Decode or abort 404 — for controllers resolving a raw hashid route param.
     */
    public static function decodeHashidOrFail(?string $hash): int
    {
        $id = static::decodeHashid($hash);

        abort_if($id === null, 404);

        return $id;
    }

    /**
     * The obfuscated public identifier for this model instance.
     */
    public function getHashidAttribute(): string
    {
        /** @var int $key */
        $key = $this->getKey();

        return static::encodeHashid($key);
    }

    /**
     * Route URLs use the hashid in place of the raw key.
     */
    public function getRouteKey(): string
    {
        /** @var int $key */
        $key = $this->getKey();

        return static::encodeHashid($key);
    }

    /**
     * Decode the hashid before constraining the binding query. Used by both
     * implicit binding and scoped child binding (`->scopeBindings()`), so a
     * single override covers nested routes like
     * `bookings/{booking}/attachments/{attachment}`.
     *
     * A non-key `$field` (explicit `:column` binding) is left untouched.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === $this->getKeyName() || $field === 'id') {
            // -1 never matches a real key, so an invalid/tampered hashid → 404.
            $decoded = static::decodeHashid((string) $value) ?? -1;

            return $query->where($this->qualifyColumn($this->getKeyName()), $decoded);
        }

        return $query->where($this->qualifyColumn($field), $value);
    }
}
