<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Actions\BlockRoomAction;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\RoomBlockSchedule;
use Illuminate\Notifications\Notification;

/**
 * In-app notification to the requester of a booking that was cancelled because
 * an admin created a room block over its slot (§H.7 force-cancel). Database
 * channel only; dispatched by BlockRoomAction after its transaction commits.
 *
 * @see BlockRoomAction
 */
final class RoomBlockCreatedNotification extends Notification
{
    public function __construct(
        private readonly RoomBlockSchedule $block,
        private readonly Booking $cancelledBooking,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => NotificationType::RoomBlockCreated->value,
            'block_id' => $this->block->id,
            'booking_id' => $this->cancelledBooking->id,
            'booking_code' => $this->cancelledBooking->booking_code,
            'room_id' => $this->block->room_id,
            'block_type' => $this->block->block_type->value,
            'message' => sprintf(
                'Reservasi %s dibatalkan karena ruang diblokir (%s).',
                $this->cancelledBooking->booking_code,
                $this->block->block_type->label(),
            ),
            'url' => route('bookings.show', $this->cancelledBooking->id),
        ];
    }
}
