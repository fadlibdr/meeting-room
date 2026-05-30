<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoomFacility;
use App\Models\User;

/**
 * Authorization for room facilities (master + per-room assignment).
 *
 * No dedicated facilities.* permissions exist in the seeder, and the §G matrix
 * for facilities is identical to Rooms, so facility management reuses the
 * rooms.* permissions (spec §2.5 / proposed Dec-19).
 *
 * Binding note for S2-C: the model name (RoomFacility) does NOT auto-discover
 * this class (auto-discovery would look for RoomFacilityPolicy). Register it
 * explicitly: Gate::policy(RoomFacility::class, FacilityPolicy::class).
 *
 * @see docs/sprint-2-room-management-spec.md (§5.3)
 */
class FacilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('rooms.view');
    }

    public function view(User $user, RoomFacility $facility): bool
    {
        return $user->hasPermission('rooms.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('rooms.create');
    }

    public function update(User $user, RoomFacility $facility): bool
    {
        return $user->hasPermission('rooms.update');
    }

    public function delete(User $user, RoomFacility $facility): bool
    {
        return $user->hasPermission('rooms.delete');
    }
}
