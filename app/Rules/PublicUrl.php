<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\PublicUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validation rule: the value must be a public, non-internal http(s) URL.
 * Used for user-supplied outbound targets (webhook subscriptions) to prevent
 * SSRF to loopback/private/link-local addresses.
 */
final class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! PublicUrlGuard::isPublicUrl($value)) {
            $fail(__('URL harus berupa alamat http(s) publik (alamat internal/lokal tidak diperbolehkan).'));
        }
    }
}
