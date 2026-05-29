<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\ActivityLog;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Lifts (cancels) an active room block: sets cancelled_at + cancelled_by and
 * flips is_active=false. The conflict service filters on is_active=true AND
 * cancelled_at IS NULL, so a lifted block immediately frees the slot.
 */
final class CancelRoomBlockAction
{
    /**
     * @throws DomainException When the block is already cancelled/inactive
     */
    public function execute(RoomBlockSchedule $block, User $actor): RoomBlockSchedule
    {
        return DB::transaction(function () use ($block, $actor): RoomBlockSchedule {
            $block = RoomBlockSchedule::query()->lockForUpdate()->findOrFail($block->id);

            if ($block->cancelled_at !== null || ! $block->is_active) {
                throw new DomainException('Blokir ruang sudah dibatalkan.');
            }

            $block->update([
                'cancelled_at' => Carbon::now(),
                'cancelled_by_user_id' => $actor->id,
                'is_active' => false,
            ]);

            ActivityLog::create([
                'actor_user_id' => $actor->id,
                'module' => 'rooms',
                'event' => 'block-cancel',
                'subject_type' => RoomBlockSchedule::class,
                'subject_id' => $block->id,
                'description' => sprintf('Blokir ruang "%s" dibatalkan oleh %s.', $block->title, $actor->name),
                'context' => ['room_id' => $block->room_id],
            ]);

            return $block->fresh() ?? $block;
        });
    }
}
