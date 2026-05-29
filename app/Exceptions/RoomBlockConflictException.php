<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\DataTransferObjects\ConflictItem;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Thrown by BlockRoomAction when a proposed room block overlaps existing
 * locking-status bookings ({submitted, approved}) and the caller did NOT opt
 * to cancel them. Mirrors BookingConflictException: carries the conflicting
 * bookings as ConflictItems so the caller can render them without re-querying.
 */
final class RoomBlockConflictException extends RuntimeException
{
    /**
     * @param  Collection<int, ConflictItem>  $conflicts
     */
    public function __construct(
        public readonly Collection $conflicts,
        string $message = 'Room block conflicts with existing bookings.',
    ) {
        parent::__construct($message);
    }
}
