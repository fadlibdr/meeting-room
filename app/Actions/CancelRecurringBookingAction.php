<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Booking;
use App\Models\User;
use DomainException;
use InvalidArgumentException;

/**
 * Cancels every still-cancellable occurrence in a booking's recurring series
 * (reusing CancelBookingAction per occurrence). Occurrences already in a
 * terminal state (cancelled/rejected/completed) are skipped. Non-recurring
 * bookings cancel just themselves.
 */
final class CancelRecurringBookingAction
{
    public function __construct(
        private readonly CancelBookingAction $cancelBooking,
    ) {}

    /**
     * @return int the number of occurrences actually cancelled
     */
    public function execute(Booking $booking, User $actor, ?string $reason = null): int
    {
        $groupId = $booking->recurrence_group_id;

        $occurrences = $groupId === null
            ? collect([$booking])
            : Booking::query()->where('recurrence_group_id', $groupId)->get();

        $cancelled = 0;
        foreach ($occurrences as $occurrence) {
            try {
                $this->cancelBooking->execute($occurrence, $actor, $reason);
                $cancelled++;
            } catch (DomainException|InvalidArgumentException) {
                // Already terminal, or otherwise not cancellable — skip it.
            }
        }

        return $cancelled;
    }
}
