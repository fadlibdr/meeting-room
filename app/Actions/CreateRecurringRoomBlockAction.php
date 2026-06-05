<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\RecurrenceFrequency;
use App\Enums\RoomBlockType;
use App\Exceptions\RoomBlockConflictException;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use App\Services\RecurrenceExpander;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Creates a recurring room-block series by materialising each occurrence via
 * BlockRoomAction (so every occurrence runs the same booking-conflict handling).
 * Occurrences that clash with bookings — when the caller did NOT opt to cancel
 * those bookings — are SKIPPED and reported; the rest are created.
 *
 * All created blocks share a self-referential recurrence_group_id pointing at
 * the first (anchor) occurrence.
 */
final class CreateRecurringRoomBlockAction
{
    public function __construct(
        private readonly BlockRoomAction $blockRoom,
        private readonly RecurrenceExpander $expander,
    ) {}

    /**
     * @return array{created: Collection<int, RoomBlockSchedule>, skipped: list<array{starts_at: string, reason: string}>}
     */
    public function execute(
        Room $room,
        User $actor,
        RoomBlockType $blockType,
        string $title,
        CarbonImmutable $startsAt,
        CarbonImmutable $endsAt,
        RecurrenceFrequency $frequency,
        int $interval = 1,
        ?CarbonImmutable $until = null,
        ?int $count = null,
        ?string $reason = null,
        bool $cancelConflictingBookings = false,
    ): array {
        $occurrences = $this->expander->expand($startsAt, $endsAt, $frequency, $interval, $until, $count);

        /** @var Collection<int, RoomBlockSchedule> $created */
        $created = collect();
        $skipped = [];

        foreach ($occurrences as $occ) {
            try {
                $created->push($this->blockRoom->execute(
                    $room,
                    $actor,
                    $blockType,
                    $title,
                    $occ['starts_at'],
                    $occ['ends_at'],
                    reason: $reason,
                    cancelConflictingBookings: $cancelConflictingBookings,
                ));
            } catch (RoomBlockConflictException) {
                $skipped[] = [
                    'starts_at' => $occ['starts_at']->format('Y-m-d H:i'),
                    'reason' => 'Bentrok dengan reservasi (aktifkan "batalkan booking bentrok" untuk menimpa)',
                ];
            }
        }

        if ($created->isNotEmpty()) {
            /** @var RoomBlockSchedule $anchor */
            $anchor = $created->first();

            RoomBlockSchedule::query()
                ->whereIn('id', $created->pluck('id')->all())
                ->update(['recurrence_group_id' => $anchor->id]);
            $created->each(static fn (RoomBlockSchedule $b) => $b->recurrence_group_id = $anchor->id);
        }

        return ['created' => $created, 'skipped' => $skipped];
    }
}
