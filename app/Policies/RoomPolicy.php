<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

/**
 * Authorization for the Room resource (Sprint 2 — Room Management).
 *
 * Mirrors BookingPolicy's permission-based approach (Phase 2 Piece 1, Q3=A):
 * keys on RBAC permissions via User::hasPermission(), not direct role checks.
 *
 * Unlike BookingPolicy, rooms have no draft/submitted lifecycle, so there are
 * no status gates on update/delete. Per Dec-06 rooms are never hard-deleted;
 * delete() therefore gates the deactivate/archive action. The non-destructive
 * handling of rooms that still have future bookings lives in the action/Livewire
 * layer (spec §2.3), not here.
 *
 * Seeded permissions: rooms.view, rooms.create, rooms.update, rooms.delete,
 * rooms.manage-blocks.
 *
 * @see docs/sprint-2-room-management-spec.md (§3, §5.2)
 */
class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('rooms.view');
    }

    public function view(User $user, Room $room): bool
    {
        return $user->hasPermission('rooms.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('rooms.create');
    }

    public function update(User $user, Room $room): bool
    {
        return $user->hasPermission('rooms.update');
    }

    /** Deactivate or archive a room (Dec-06: replaces hard-delete). */
    public function delete(User $user, Room $room): bool
    {
        return $user->hasPermission('rooms.delete');
    }

    /** Create or cancel room block schedules (spec §2.6). Room optional: the
     *  create-block screen authorizes before a specific room is chosen. */
    public function manageBlocks(User $user, ?Room $room = null): bool
    {
        return $user->hasPermission('rooms.manage-blocks');
    }
}
