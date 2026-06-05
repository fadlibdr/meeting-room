<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\RoomBlockSchedule;
use App\Models\User;
use DomainException;

/**
 * Cancels every active occurrence in a room-block series (reusing
 * CancelRoomBlockAction per occurrence). Already-cancelled occurrences are
 * skipped. A non-recurring block cancels just itself.
 */
final class CancelRecurringRoomBlockAction
{
    public function __construct(
        private readonly CancelRoomBlockAction $cancelBlock,
    ) {}

    /**
     * @return int the number of occurrences actually cancelled
     */
    public function execute(RoomBlockSchedule $block, User $actor): int
    {
        $groupId = $block->recurrence_group_id;

        $query = $groupId === null
            ? RoomBlockSchedule::query()->whereKey($block->id)
            : RoomBlockSchedule::query()->where('recurrence_group_id', $groupId);

        $occurrences = $query->where('is_active', true)->whereNull('cancelled_at')->get();

        $cancelled = 0;
        foreach ($occurrences as $occurrence) {
            try {
                $this->cancelBlock->execute($occurrence, $actor);
                $cancelled++;
            } catch (DomainException) {
                // Already cancelled — skip.
            }
        }

        return $cancelled;
    }
}
