<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Writes a memory-safe XLSX export of bookings to a local file path.
 *
 * XLSX is a zip container and cannot be streamed to php://output incrementally
 * the way CSV can, so callers write to a temp/disk file and then serve it.
 * openspout itself streams rows to disk, keeping memory flat over large cursors.
 * Column layout is shared with the CSV path via BookingExportRowMapper.
 */
final class BookingXlsxExporter
{
    public function __construct(
        private readonly BookingExportRowMapper $mapper = new BookingExportRowMapper,
    ) {}

    /**
     * @param  iterable<Booking>  $bookings
     * @return int Number of data rows written (excludes the header).
     */
    public function writeToFile(string $path, iterable $bookings, string $timezone): int
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $writer->addRow(Row::fromValues($this->mapper->header()));

        $count = 0;
        foreach ($bookings as $booking) {
            $writer->addRow(Row::fromValues($this->mapper->row($booking, $timezone)));
            $count++;
        }

        $writer->close();

        return $count;
    }
}
