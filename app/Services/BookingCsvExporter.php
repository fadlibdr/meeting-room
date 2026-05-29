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
 * read it back). Timestamps render in the supplied display timezone.
 */
final class BookingCsvExporter
{
    /**
     * @param  resource  $handle
     * @param  iterable<Booking>  $bookings
     */
    public function writeCsv($handle, iterable $bookings, string $timezone): void
    {
        fputcsv($handle, [
            'Kode Booking',
            'Ruang',
            'Pemohon',
            'Unit',
            'Subjek',
            'Jumlah Peserta',
            'Mulai',
            'Selesai',
            'Status',
        ]);

        foreach ($bookings as $booking) {
            fputcsv($handle, [
                $booking->booking_code,
                data_get($booking, 'room.name', ''),
                data_get($booking, 'requester.name', ''),
                data_get($booking, 'requesterUnit.name', ''),
                $booking->subject,
                $booking->attendee_count,
                $booking->starts_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
                $booking->ends_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
                $booking->status->label(),
            ]);
        }
    }
}
