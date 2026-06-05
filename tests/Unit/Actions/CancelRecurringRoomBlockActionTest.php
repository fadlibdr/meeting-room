<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CancelRecurringRoomBlockAction;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelRecurringRoomBlockActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_every_active_occurrence_and_skips_cancelled_ones(): void
    {
        $room = Room::factory()->create(['is_active' => true, 'status' => 'active']);
        $actor = User::factory()->create();

        $anchor = RoomBlockSchedule::factory()->create(['room_id' => $room->id, 'is_active' => true]);
        $anchor->update(['recurrence_group_id' => $anchor->id]);
        $active = RoomBlockSchedule::factory()->create(['room_id' => $room->id, 'is_active' => true, 'recurrence_group_id' => $anchor->id]);
        $cancelled = RoomBlockSchedule::factory()->cancelled()->create(['room_id' => $room->id, 'recurrence_group_id' => $anchor->id]);

        $count = app(CancelRecurringRoomBlockAction::class)->execute($anchor, $actor);

        $this->assertSame(2, $count);
        $this->assertFalse($anchor->fresh()->is_active);
        $this->assertNotNull($anchor->fresh()->cancelled_at);
        $this->assertFalse($active->fresh()->is_active);
        $this->assertNotNull($cancelled->fresh()->cancelled_at); // untouched
    }
}
