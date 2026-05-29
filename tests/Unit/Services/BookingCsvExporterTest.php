<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Services\BookingCsvExporter;
use Illuminate\Support\Collection;
use Tests\TestCase;

class BookingCsvExporterTest extends TestCase
{
    private function makeBooking(string $subject = 'Rapat Koordinasi'): Booking
    {
        $room = new Room;
        $room->name = 'Ruang Garuda';

        $requester = new User;
        $requester->name = 'Budi Santoso';

        $unit = new Unit;
        $unit->name = 'Divisi IT';

        $booking = new Booking;
        $booking->booking_code = 'BKG-20260505-0001';
        $booking->subject = $subject;
        $booking->attendee_count = 8;
        $booking->starts_at = '2026-05-05 02:00:00'; // UTC -> 09:00 WIB
        $booking->ends_at = '2026-05-05 03:00:00';   // UTC -> 10:00 WIB
        $booking->status = BookingStatus::Approved;
        $booking->setRelation('room', $room);
        $booking->setRelation('requester', $requester);
        $booking->setRelation('requesterUnit', $unit);

        return $booking;
    }

    /**
     * Returns parsed CSV rows (fields per line). Asserting parsed fields rather
     * than the raw string keeps these robust to fputcsv quoting (it quotes any
     * field containing a space).
     *
     * @param  Collection<int, Booking>  $bookings
     * @return list<array<int, string|null>>
     */
    private function rowsFor(Collection $bookings): array
    {
        $handle = fopen('php://temp', 'r+');
        (new BookingCsvExporter)->writeCsv($handle, $bookings, 'Asia/Jakarta');
        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        $lines = array_values(array_filter(explode("\n", $csv), static fn ($l) => $l !== ''));

        return array_map(static fn (string $line) => str_getcsv($line), $lines);
    }

    public function test_writes_header_and_row_with_jakarta_times(): void
    {
        $rows = $this->rowsFor(collect([$this->makeBooking()]));

        $this->assertSame(
            ['Kode Booking', 'Ruang', 'Pemohon', 'Unit', 'Subjek', 'Jumlah Peserta', 'Mulai', 'Selesai', 'Status'],
            $rows[0],
        );
        $this->assertSame(
            ['BKG-20260505-0001', 'Ruang Garuda', 'Budi Santoso', 'Divisi IT', 'Rapat Koordinasi', '8', '2026-05-05 09:00', '2026-05-05 10:00', 'Disetujui'],
            $rows[1],
        );
    }

    public function test_preserves_fields_containing_commas(): void
    {
        $rows = $this->rowsFor(collect([$this->makeBooking('Rapat A, B, C')]));

        $this->assertSame('Rapat A, B, C', $rows[1][4]);
    }

    public function test_includes_a_data_row_per_booking(): void
    {
        $rows = $this->rowsFor(collect([$this->makeBooking(), $this->makeBooking('Rapat Lain')]));

        $this->assertCount(3, $rows); // header + 2 data rows
        $this->assertSame('Rapat Koordinasi', $rows[1][4]);
        $this->assertSame('Rapat Lain', $rows[2][4]);
    }

    public function test_emits_only_a_header_for_no_bookings(): void
    {
        $rows = $this->rowsFor(collect([]));

        $this->assertCount(1, $rows);
        $this->assertSame('Kode Booking', $rows[0][0]);
    }
}
