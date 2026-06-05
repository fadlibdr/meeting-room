<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AppSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

class SettingsService
{
    private const CACHE_PREFIX = 'app_settings.';

    /**
     * Get a setting value with fallback chain:
     * 1. Cached value
     * 2. DB row (casted via getCastedValue)
     * 3. Config fallback (key translated: booking.X -> meeting_room.X)
     * 4. Provided default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $cacheKey = self::CACHE_PREFIX.$key;

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached === '__NULL__' ? null : $cached;
        }

        $setting = AppSetting::where('key', $key)->first();
        if ($setting !== null) {
            $value = $setting->getCastedValue();
            // Never cache decrypted secrets at rest — only structural settings.
            if ($setting->data_type !== 'encrypted') {
                Cache::forever($cacheKey, $value ?? '__NULL__');
            }

            return $value;
        }

        // Config fallback: e.g., 'booking.default_buffer_minutes' -> 'meeting_room.default_buffer_minutes'
        $configKey = $this->translateKeyToConfig($key);
        $configValue = config($configKey);
        if ($configValue !== null) {
            return $configValue;
        }

        return $default;
    }

    /**
     * Set or update a setting. Invalidates cache.
     */
    public function set(string $key, mixed $value, ?int $updatedBy = null): AppSetting
    {
        $setting = AppSetting::where('key', $key)->first();

        if ($setting === null) {
            throw new \RuntimeException("Setting key '{$key}' does not exist. Settings must be seeded first.");
        }

        if (! $setting->is_editable) {
            throw new \RuntimeException("Setting '{$key}' is marked read-only.");
        }

        $setting->value = $this->serializeValue($value, $setting->data_type);
        $setting->updated_by_user_id = $updatedBy;
        $setting->save();

        Cache::forget(self::CACHE_PREFIX.$key);

        return $setting;
    }

    /**
     * Get all settings, optionally filtered by group.
     *
     * @return Collection<int, AppSetting>
     */
    public function getAll(?string $group = null): Collection
    {
        $query = AppSetting::query()->orderBy('group')->orderBy('key');

        if ($group !== null) {
            $query->where('group', $group);
        }

        return $query->get();
    }

    /**
     * Forget cached value for a key (forces re-read from DB on next get).
     */
    public function forget(string $key): void
    {
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * Forget all cached settings.
     */
    public function flushCache(): void
    {
        AppSetting::pluck('key')->each(function (string $key): void {
            Cache::forget(self::CACHE_PREFIX.$key);
        });
    }

    /**
     * Translate setting key to config key.
     * 'booking.default_buffer_minutes' -> 'meeting_room.default_buffer_minutes'
     */
    private function translateKeyToConfig(string $key): string
    {
        // Strip leading namespace, prefix with 'meeting_room'
        $parts = explode('.', $key, 2);
        if (count($parts) === 2) {
            return 'meeting_room.'.$parts[1];
        }

        return 'meeting_room.'.$key;
    }

    /**
     * Serialize a typed value back to string for DB storage.
     */
    private function serializeValue(mixed $value, string $dataType): string
    {
        return match ($dataType) {
            'integer' => (string) (int) $value,
            'boolean' => $value ? '1' : '0',
            'json' => json_encode($value, JSON_UNESCAPED_UNICODE) ?: '',
            'encrypted' => Crypt::encryptString((string) $value),
            default => (string) $value,
        };
    }
}
