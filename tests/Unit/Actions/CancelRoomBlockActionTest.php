<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CancelRoomBlockAction;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use App\Services\BookingConflictService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CancelRoomBlockActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_an_active_block(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $block = RoomBlockSchedule::factory()->create(['room_id' => $room->id, 'is_active' => true, 'cancelled_at' => null]);

        (new CancelRoomBlockAction)->execute($block, $actor);

        $block->refresh();
        $this->assertFalse($block->is_active);
        $this->assertNotNull($block->cancelled_at);
        $this->assertSame($actor->id, $block->cancelled_by_user_id);
    }

    public function test_refuses_to_cancel_an_already_cancelled_block(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $block = RoomBlockSchedule::factory()->cancelled()->create(['room_id' => $room->id]);

        $this->expectException(DomainException::class);

        (new CancelRoomBlockAction)->execute($block, $actor);
    }

    public function test_writes_activity_log_for_block_cancel(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $block = RoomBlockSchedule::factory()->create(['room_id' => $room->id, 'is_active' => true, 'cancelled_at' => null]);

        (new CancelRoomBlockAction)->execute($block, $actor);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'rooms',
            'event' => 'block-cancel',
            'subject_type' => RoomBlockSchedule::class,
            'subject_id' => $block->id,
            'actor_user_id' => $actor->id,
        ]);
    }

    public function test_cancelling_a_block_frees_the_slot(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $start = Carbon::parse('2026-06-01 10:00:00');
        $end = Carbon::parse('2026-06-01 12:00:00');
        $block = RoomBlockSchedule::factory()->create([
            'room_id' => $room->id,
            'starts_at' => $start,
            'ends_at' => $end,
            'is_active' => true,
            'cancelled_at' => null,
        ]);

        $service = app(BookingConflictService::class);
        $this->assertTrue($service->findConflicts($room, $start, $end)->isNotEmpty());

        (new CancelRoomBlockAction)->execute($block, $actor);

        $this->assertTrue($service->findConflicts($room, $start, $end)->isEmpty());
    }
}
