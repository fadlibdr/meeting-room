<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Booking;
use App\Models\Room;
use App\Services\IcsGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IcsGeneratorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function booking(array $attrs = []): Booking
    {
        $room = Room::factory()->create(['name' => 'Ruang Garuda', 'location' => 'Gedung A']);

        return Booking::factory()->create(array_merge([
            'resource_id' => $room->id,
            'booking_code' => 'BKG-20260606-TEST',
            'subject' => 'Rapat',
            'starts_at' => '2026-06-08 03:00:00', // stored UTC
            'ends_at' => '2026-06-08 04:00:00',
        ], $attrs));
    }

    public function test_emits_a_vevent_with_utc_times(): void
    {
        $ics = (new IcsGenerator)->forBooking($this->booking());

        $this->assertStringContainsString('BEGIN:VCALENDAR', $ics);
        $this->assertStringContainsString('BEGIN:VEVENT', $ics);
        $this->assertStringContainsString('DTSTART:20260608T030000Z', $ics);
        $this->assertStringContainsString('DTEND:20260608T040000Z', $ics);
        $this->assertStringContainsString('SUMMARY:Rapat', $ics);
        $this->assertStringEndsWith("END:VCALENDAR\r\n", $ics);
    }

    public function test_escapes_special_characters(): void
    {
        $ics = (new IcsGenerator)->forBooking($this->booking(['subject' => 'A, B; C\\ D']));

        // , ; \ are escaped per RFC 5545 §3.3.11
        $this->assertStringContainsString('SUMMARY:A\\, B\\; C\\\\ D', $ics);
    }

    public function test_escapes_newlines_in_description(): void
    {
        $ics = (new IcsGenerator)->forBooking($this->booking(['agenda' => "Baris 1\nBaris 2"]));

        $this->assertStringContainsString('Baris 1\\nBaris 2', $ics);
    }

    public function test_folds_long_lines_to_75_octets(): void
    {
        // 140-char subject → SUMMARY line ~148 octets → must fold (column max is 150).
        $ics = (new IcsGenerator)->forBooking($this->booking(['subject' => str_repeat('X', 140)]));

        $this->assertStringContainsString("\r\n ", $ics, 'A long line must be folded.');
        foreach (explode("\r\n", $ics) as $physicalLine) {
            $this->assertLessThanOrEqual(75, strlen($physicalLine), 'No content line may exceed 75 octets.');
        }
    }

    public function test_folding_is_utf8_safe(): void
    {
        // 60 × 2-octet chars = 120 octets → must fold without splitting a character.
        $ics = (new IcsGenerator)->forBooking($this->booking(['subject' => str_repeat('é', 60)]));

        $this->assertNotFalse(mb_detect_encoding($ics, 'UTF-8', true), 'Folded output must remain valid UTF-8.');
        foreach (explode("\r\n", $ics) as $physicalLine) {
            $this->assertLessThanOrEqual(75, strlen($physicalLine));
        }
    }
}
