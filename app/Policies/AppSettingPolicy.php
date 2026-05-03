<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AppSetting;
use App\Models\User;

class AppSettingPolicy
{
    /**
     * Determine if the user can view any settings (admin index page).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('app-settings.view');
    }

    /**
     * Determine if the user can view a specific setting row.
     */
    public function view(User $user, AppSetting $setting): bool
    {
        return $user->hasPermission('app-settings.view');
    }

    /**
     * Determine if the user can update a setting.
     *
     * Updates require the permission AND the setting must be marked editable.
     * System-managed settings (is_editable=false) cannot be modified via UI.
     */
    public function update(User $user, AppSetting $setting): bool
    {
        return $user->hasPermission('app-settings.update') && $setting->is_editable;
    }

    // No create() or delete() methods - settings are seed-only.
    // Users cannot add or remove keys via the application.
}
