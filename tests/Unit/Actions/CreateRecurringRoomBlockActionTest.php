<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CreateRecurringRoomBlockAction;
use App\Enums\RecurrenceFrequency;
use App\Enums\RoomBlockType;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRecurringRoomBlockActionTest extends TestCase
{
    use RefreshDatabase;

    private function room(): Room
    {
        return Room::factory()->create(['is_active' => true, 'status' => 'active']);
    }

    public function test_creates_a_block_series_sharing_a_recurrence_group(): void
    {
        $room = $this->room();
        $actor = User::factory()->create();

        $result = app(CreateRecurringRoomBlockAction::class)->execute(
            $room, $actor, RoomBlockType::Maintenance, 'Pemeliharaan AC',
            CarbonImmutable::parse('2026-06-08 08:00:00'),
            CarbonImmutable::parse('2026-06-08 10:00:00'),
            RecurrenceFrequency::Weekly, 1, null, 3,
        );

        $this->assertCount(3, $result['created']);
        $this->assertSame([], $result['skipped']);

        $anchorId = $result['created']->first()->id;
        foreach ($result['created'] as $block) {
            $this->assertSame($anchorId, $block->recurrence_group_id);
        }
        $this->assertSame(3, RoomBlockSchedule::query()->where('recurrence_group_id', $anchorId)->count());
    }

    public function test_occurrence_conflicting_with_a_booking_is_skipped_when_not_cancelling(): void
    {
        $room = $this->room();
        $actor = User::factory()->create();

        // Approved booking inside the 2nd weekly block window (2026-06-15 08:00–10:00).
        Booking::factory()->create([
            'resource_id' => $room->id,
            'status' => 'approved',
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 09:30:00',
        ]);

        $result = app(CreateRecurringRoomBlockAction::class)->execute(
            $room, $actor, RoomBlockType::Maintenance, 'Pemeliharaan AC',
            CarbonImmutable::parse('2026-06-08 08:00:00'),
            CarbonImmutable::parse('2026-06-08 10:00:00'),
            RecurrenceFrequency::Weekly, 1, null, 3,
            reason: null, cancelConflictingBookings: false,
        );

        $this->assertCount(2, $result['created']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('2026-06-15', $result['skipped'][0]['starts_at']);

        // The conflicting booking survives because we chose NOT to cancel.
        $this->assertSame('approved', Booking::query()->first()?->status->value);
    }

    public function test_occurrence_cancels_conflicting_booking_when_opted_in(): void
    {
        $room = $this->room();
        $actor = User::factory()->create();

        $booking = Booking::factory()->create([
            'resource_id' => $room->id,
            'status' => 'approved',
            'starts_at' => '2026-06-15 09:00:00',
            'ends_at' => '2026-06-15 09:30:00',
        ]);

        $result = app(CreateRecurringRoomBlockAction::class)->execute(
            $room, $actor, RoomBlockType::Maintenance, 'Pemeliharaan AC',
            CarbonImmutable::parse('2026-06-08 08:00:00'),
            CarbonImmutable::parse('2026-06-08 10:00:00'),
            RecurrenceFrequency::Weekly, 1, null, 3,
            reason: null, cancelConflictingBookings: true,
        );

        $this->assertCount(3, $result['created']);
        $this->assertSame([], $result['skipped']);
        $this->assertSame('cancelled', $booking->fresh()->status->value);
    }
}
