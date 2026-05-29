<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\ConflictItem;
use App\Enums\BookingStatus;
use App\Enums\RoomBlockType;
use App\Exceptions\RoomBlockConflictException;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates a room block, resolving conflicts with existing bookings per §H.7.
 *
 * Conflict set (Dec-16): {submitted, approved} bookings overlapping the block
 * window, NO buffer (blocks are hard — same overlap rule the conflict service
 * uses for blocks-vs-bookings).
 *
 * Resolutions (the third §H.7 option, "adjust", is just the caller re-running
 * with new times, so it needs no mode here):
 *  - $cancelConflictingBookings = false (default): throw RoomBlockConflictException
 *    carrying the conflicts; nothing is written.
 *  - $cancelConflictingBookings = true: cancel each conflicting booking via
 *    CancelBookingAction (which notifies its approver), then create the block.
 *
 * Atomicity: one DB::transaction with the room row locked (lockForUpdate) so a
 * concurrent SubmitBookingAction — which also locks the room — cannot slip a
 * new booking into the window mid-flight. CancelBookingAction's nested
 * transaction is a savepoint and its synchronous notification write joins this
 * transaction, so force-cancel + block-create are all-or-nothing.
 */
final class BlockRoomAction
{
    public function __construct(
        private readonly CancelBookingAction $cancelBooking,
    ) {}

    /**
     * @throws InvalidArgumentException When ends_at is not after starts_at
     * @throws RoomBlockConflictException When overlapping bookings exist and
     *                                    $cancelConflictingBookings is false
     */
    public function execute(
        Room $room,
        User $actor,
        RoomBlockType $blockType,
        string $title,
        CarbonInterface $startsAt,
        CarbonInterface $endsAt,
        ?string $reason = null,
        bool $cancelConflictingBookings = false,
    ): RoomBlockSchedule {
        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new InvalidArgumentException('Waktu selesai blokir harus setelah waktu mulai.');
        }

        return DB::transaction(function () use (
            $room,
            $actor,
            $blockType,
            $title,
            $startsAt,
            $endsAt,
            $reason,
            $cancelConflictingBookings,
        ): RoomBlockSchedule {
            // Lock the room so concurrent submissions can't race the block.
            $room = Room::query()->lockForUpdate()->findOrFail($room->id);

            // Overlapping locking-status bookings (no buffer for blocks).
            $conflicts = Booking::query()
                ->where('room_id', $room->id)
                ->whereIn('status', [BookingStatus::Submitted->value, BookingStatus::Approved->value])
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt)
                ->lockForUpdate()
                ->get();

            if ($conflicts->isNotEmpty() && ! $cancelConflictingBookings) {
                throw new RoomBlockConflictException(
                    $conflicts->map(fn (Booking $b): ConflictItem => new ConflictItem(
                        type: ConflictItem::TYPE_BOOKING,
                        title: $b->subject,
                        startsAt: $b->starts_at,
                        endsAt: $b->ends_at,
                        reference: $b,
                    ))
                );
            }

            $cancelledIds = [];
            foreach ($conflicts as $booking) {
                $this->cancelBooking->execute(
                    $booking,
                    $actor,
                    reason: sprintf('Dibatalkan otomatis: ruang diblokir (%s).', $blockType->label()),
                    notify: true,
                );
                $cancelledIds[] = $booking->id;
            }

            $block = RoomBlockSchedule::create([
                'room_id' => $room->id,
                'block_type' => $blockType,
                'title' => $title,
                'reason' => $reason,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by_user_id' => $actor->id,
                'is_active' => true,
            ]);

            ActivityLog::create([
                'actor_user_id' => $actor->id,
                'module' => 'rooms',
                'event' => 'block-create',
                'subject_type' => RoomBlockSchedule::class,
                'subject_id' => $block->id,
                'description' => sprintf(
                    'Ruang %s diblokir (%s): %s.',
                    $room->name,
                    $blockType->label(),
                    $title,
                ),
                'context' => [
                    'room_id' => $room->id,
                    'block_type' => $blockType->value,
                    'starts_at' => $startsAt->format('Y-m-d H:i:s'),
                    'ends_at' => $endsAt->format('Y-m-d H:i:s'),
                    'cancelled_booking_ids' => $cancelledIds,
                ],
            ]);

            return $block;
        });
    }
}
