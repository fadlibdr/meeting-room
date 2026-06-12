<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Booking;
use App\Support\CsvSanitizer;

/**
 * Single source of truth for the column layout of a bookings export, shared by
 * the CSV and XLSX exporters so the two formats never drift. Timestamps render
 * in the supplied display timezone.
 */
final class BookingExportRowMapper
{
    /**
     * @return list<string>
     */
    public function header(): array
    {
        return [
            'Kode Booking',
            'Ruang',
            'Pemohon',
            'Unit',
            'Subjek',
            'Jumlah Peserta',
            'Mulai',
            'Selesai',
            'Status',
        ];
    }

    /**
     * @return list<string>
     */
    public function row(Booking $booking, string $timezone): array
    {
        // Neutralize spreadsheet formula injection in user-controlled cells
        // (subject, room/requester/unit names).
        return CsvSanitizer::row([
            (string) $booking->booking_code,
            (string) data_get($booking, 'room.name', ''),
            (string) data_get($booking, 'requester.name', ''),
            (string) data_get($booking, 'requesterUnit.name', ''),
            (string) $booking->subject,
            (string) $booking->attendee_count,
            $booking->starts_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
            $booking->ends_at->copy()->setTimezone($timezone)->format('Y-m-d H:i'),
            $booking->status->label(),
        ]);
    }
}
