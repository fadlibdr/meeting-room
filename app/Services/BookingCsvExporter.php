<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;

/**
 * Streams a CSV representation of bookings to an open file handle.
 *
 * Writing to an injected handle (rather than returning a string) keeps the
 * export memory-safe when driven by a query cursor over large result sets,
 * and keeps the formatting independently unit-testable (write to php://temp,
 * read it back). Column layout is owned by BookingExportRowMapper so CSV and
 * XLSX stay in lockstep. Timestamps render in the supplied display timezone.
 */
final class BookingCsvExporter
{
    public function __construct(
        private readonly BookingExportRowMapper $mapper = new BookingExportRowMapper,
    ) {}

    /**
     * @param  resource  $handle
     * @param  iterable<Booking>  $bookings
     */
    public function writeCsv($handle, iterable $bookings, string $timezone): void
    {
        fputcsv($handle, $this->mapper->header());

        foreach ($bookings as $booking) {
            fputcsv($handle, $this->mapper->row($booking, $timezone));
        }
    }
}
