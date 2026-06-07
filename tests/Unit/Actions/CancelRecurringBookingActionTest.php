<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\CancelRecurringBookingAction;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CancelRecurringBookingActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancels_every_active_occurrence_and_skips_terminal_ones(): void
    {
        $room = Room::factory()->create(['is_active' => true, 'status' => 'active']);
        $actor = User::factory()->create();

        $anchor = Booking::factory()->create(['resource_id' => $room->id, 'status' => 'approved']);
        $anchor->update(['recurrence_group_id' => $anchor->id]);
        $draft = Booking::factory()->create(['resource_id' => $room->id, 'status' => 'draft', 'recurrence_group_id' => $anchor->id]);
        $alreadyCancelled = Booking::factory()->create(['resource_id' => $room->id, 'status' => 'cancelled', 'recurrence_group_id' => $anchor->id]);

        $count = app(CancelRecurringBookingAction::class)->execute($anchor, $actor, 'Rapat tidak jadi');

        $this->assertSame(2, $count); // anchor (approved) + draft
        $this->assertSame('cancelled', $anchor->fresh()->status->value);
        $this->assertSame('cancelled', $draft->fresh()->status->value);
        $this->assertSame('cancelled', $alreadyCancelled->fresh()->status->value); // untouched
    }

    public function test_non_recurring_booking_cancels_only_itself(): void
    {
        $room = Room::factory()->create(['is_active' => true, 'status' => 'active']);
        $actor = User::factory()->create();

        $solo = Booking::factory()->create(['resource_id' => $room->id, 'status' => 'draft', 'recurrence_group_id' => null]);
        $other = Booking::factory()->create(['resource_id' => $room->id, 'status' => 'draft', 'recurrence_group_id' => null]);

        $count = app(CancelRecurringBookingAction::class)->execute($solo, $actor, 'Batal');

        $this->assertSame(1, $count);
        $this->assertSame('cancelled', $solo->fresh()->status->value);
        $this->assertSame('draft', $other->fresh()->status->value);
    }
}
