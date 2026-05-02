<?php

declare(strict_types=1);

namespace App\Observers;

use App\Facades\Activity;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;

class UserRoleObserver
{
    public function created(UserRole $userRole): void
    {
        $user = $userRole->user;
        $role = $userRole->role;

        if (! $user instanceof User || ! $role instanceof Role) {
            return;
        }

        $actor = $this->resolveActor($userRole);
        $primaryNote = $userRole->is_primary ? ' (set as primary)' : '';

        Activity::log(
            module: 'users',
            event: 'role_assigned',
            subject: $user,
            payload: [
                'description' => "Role '{$role->name}' assigned to {$user->email}{$primaryNote}",
                'context' => [
                    'role_id' => $role->id,
                    'role_code' => $role->code,
                    'role_name' => $role->name,
                    'is_primary' => $userRole->is_primary,
                ],
            ],
            actor: $actor
        );
    }

    public function updated(UserRole $userRole): void
    {
        $changes = $userRole->getChanges();

        // We only care about is_primary changes — other fields shouldn't normally update
        if (! array_key_exists('is_primary', $changes)) {
            return;
        }

        $user = $userRole->user;
        $role = $userRole->role;

        if (! $user instanceof User || ! $role instanceof Role) {
            return;
        }

        $original = $userRole->getOriginal();
        $actor = $this->resolveActor($userRole);

        Activity::log(
            module: 'users',
            event: 'role_primary_changed',
            subject: $user,
            payload: [
                'description' => "Role '{$role->name}' primary flag changed for {$user->email}",
                'old_values' => ['is_primary' => $original['is_primary'] ?? null],
                'new_values' => ['is_primary' => $userRole->is_primary],
                'context' => [
                    'role_id' => $role->id,
                    'role_code' => $role->code,
                ],
            ],
            actor: $actor
        );
    }

    public function deleted(UserRole $userRole): void
    {
        $user = $userRole->user;
        $role = $userRole->role;

        if (! $user instanceof User || ! $role instanceof Role) {
            return;
        }

        $actor = $this->resolveActor($userRole);

        Activity::log(
            module: 'users',
            event: 'role_revoked',
            subject: $user,
            payload: [
                'description' => "Role '{$role->name}' revoked from {$user->email}",
                'context' => [
                    'role_id' => $role->id,
                    'role_code' => $role->code,
                    'role_name' => $role->name,
                ],
            ],
            actor: $actor
        );
    }

    /**
     * Resolve the actor: prefer explicit assigned_by_user_id, fall back to currently auth'd user.
     */
    private function resolveActor(UserRole $userRole): ?User
    {
        if ($userRole->assigned_by_user_id) {
            $explicit = User::find($userRole->assigned_by_user_id);
            if ($explicit instanceof User) {
                return $explicit;
            }
        }

        // Falls back to Activity::log's auth resolution if we return null
        return null;
    }
}
