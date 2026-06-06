<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Services\BookingXlsxExporter;
use OpenSpout\Reader\XLSX\Reader;
use Tests\TestCase;

class BookingXlsxExporterTest extends TestCase
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
     * @return list<list<mixed>>
     */
    private function readBack(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);

        $rows = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            break;
        }
        $reader->close();

        return $rows;
    }

    public function test_writes_header_and_data_rows_to_a_valid_xlsx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsxtest').'.xlsx';

        $count = (new BookingXlsxExporter)->writeToFile(
            $path,
            [$this->makeBooking(), $this->makeBooking('Rapat Lain')],
            'Asia/Jakarta',
        );

        $this->assertSame(2, $count);
        $this->assertFileExists($path);

        $rows = $this->readBack($path);
        $this->assertCount(3, $rows); // header + 2 data rows
        $this->assertSame(
            ['Kode Booking', 'Ruang', 'Pemohon', 'Unit', 'Subjek', 'Jumlah Peserta', 'Mulai', 'Selesai', 'Status'],
            $rows[0],
        );
        $this->assertSame('BKG-20260505-0001', $rows[1][0]);
        $this->assertSame('Ruang Garuda', $rows[1][1]);
        $this->assertSame('2026-05-05 09:00', $rows[1][6]);
        $this->assertSame('Disetujui', $rows[1][8]);
        $this->assertSame('Rapat Lain', $rows[2][4]);

        @unlink($path);
    }

    public function test_emits_only_a_header_for_no_bookings(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsxtest').'.xlsx';

        $count = (new BookingXlsxExporter)->writeToFile($path, [], 'Asia/Jakarta');

        $this->assertSame(0, $count);
        $rows = $this->readBack($path);
        $this->assertCount(1, $rows);
        $this->assertSame('Kode Booking', $rows[0][0]);

        @unlink($path);
    }
}
