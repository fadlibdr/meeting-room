<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;

class PermissionCacheService
{
    private const CACHE_PREFIX = 'user_permissions:';

    private const CACHE_TTL_SECONDS = 3600; // 1 hour

    /**
     * Get all permission codes for a user, cached.
     *
     * @return array<int, string>
     */
    public function getPermissions(User $user): array
    {
        $key = $this->cacheKey($user);

        return Cache::remember(
            $key,
            self::CACHE_TTL_SECONDS,
            fn () => $this->loadPermissions($user)
        );
    }

    /**
     * Check if a user has a specific permission code.
     */
    public function userHas(User $user, string $permission): bool
    {
        return in_array($permission, $this->getPermissions($user), true);
    }

    /**
     * Clear the cached permissions for a user.
     * Called when role assignments change.
     */
    public function forget(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    /**
     * Clear cached permissions for all users with a specific role.
     * Called when a role's permission set changes.
     */
    public function forgetByRole(int $roleId): void
    {
        $userIds = User::whereHas('roles', fn ($q) => $q->where('roles.id', $roleId))
            ->pluck('id');

        foreach ($userIds as $userId) {
            Cache::forget(self::CACHE_PREFIX.$userId);
        }
    }

    /**
     * Load permission codes from the database.
     *
     * @return array<int, string>
     */
    private function loadPermissions(User $user): array
    {
        return $user->roles()
            ->where('roles.is_active', true)
            ->with(['permissions' => fn ($q) => $q->where('permissions.is_active', true)])
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->unique()
            ->values()
            ->all();
    }

    private function cacheKey(User $user): string
    {
        return self::CACHE_PREFIX.$user->id;
    }
}
