<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\DataTransferObjects\ConflictItem;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Thrown when BookingConflictService detects time/space conflicts that
 * prevent a booking from being placed in the requested slot.
 *
 * Carries the conflicts as a public readonly property so the caller
 * (Action layer) can inspect them and route to UI/API responses
 * without re-querying.
 */
final class BookingConflictException extends RuntimeException
{
    /**
     * @param  Collection<int, ConflictItem>  $conflicts
     */
    public function __construct(
        public readonly Collection $conflicts,
        string $message = 'Booking conflicts detected.',
    ) {
        parent::__construct($message);
    }
}
