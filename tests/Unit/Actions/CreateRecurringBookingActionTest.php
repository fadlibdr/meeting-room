<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CreateRecurringBookingAction;
use App\Enums\BookingStatus;
use App\Enums\RecurrenceFrequency;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRecurringBookingActionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{room_id:int, subject:string, agenda:null, attendee_count:int, starts_at:string, ends_at:string}
     */
    private function input(Room $room): array
    {
        return [
            'room_id' => $room->id,
            'subject' => 'Rapat Mingguan',
            'agenda' => null,
            'attendee_count' => 3,
            // 2026-06-08 is a Monday.
            'starts_at' => '2026-06-08 10:00:00',
            'ends_at' => '2026-06-08 11:00:00',
        ];
    }

    private function room(): Room
    {
        return Room::factory()->create([
            'approval_mode' => 'none',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);
    }

    public function test_creates_a_series_sharing_a_recurrence_group(): void
    {
        $room = $this->room();
        $requester = User::factory()->create();

        $result = app(CreateRecurringBookingAction::class)->execute(
            $requester, $this->input($room), RecurrenceFrequency::Weekly, 1, null, 4
        );

        $this->assertCount(4, $result['created']);
        $this->assertSame([], $result['skipped']);

        $anchorId = $result['created']->first()->id;
        foreach ($result['created'] as $booking) {
            $this->assertSame($anchorId, $booking->recurrence_group_id);
            $this->assertSame(BookingStatus::Approved, $booking->status); // room is None mode
        }

        $this->assertSame(4, Booking::query()->where('recurrence_group_id', $anchorId)->count());
    }

    public function test_conflicting_occurrence_is_skipped_not_created(): void
    {
        $room = $this->room();
        $requester = User::factory()->create();

        // Pre-existing approved booking overlapping the 2nd weekly occurrence.
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'approved',
            'starts_at' => '2026-06-15 10:00:00',
            'ends_at' => '2026-06-15 11:00:00',
        ]);

        $result = app(CreateRecurringBookingAction::class)->execute(
            $requester, $this->input($room), RecurrenceFrequency::Weekly, 1, null, 4
        );

        $this->assertCount(3, $result['created']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('2026-06-15', $result['skipped'][0]['starts_at']);
        $this->assertStringContainsString('reservasi', $result['skipped'][0]['reason']);
    }
}
