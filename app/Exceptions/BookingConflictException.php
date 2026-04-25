<?php

namespace App\Exceptions;

use Exception;

class BookingConflictException extends Exception
{
    public function __construct(
        string $message = 'Slot yang Anda pilih sudah tidak tersedia.',
        public ?array $conflictingBookingIds = null,
    ) {
        parent::__construct($message);
    }
}
