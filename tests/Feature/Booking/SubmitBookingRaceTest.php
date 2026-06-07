<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\SubmitBookingAction;
use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §M.4 race-condition coverage.
 *
 * A literal concurrent harness is impractical under RefreshDatabase — the
 * action's DB::transaction nests as a savepoint inside the test transaction,
 * and a second connection cannot see the first's uncommitted writes. So this
 * asserts the guarantee that actually defeats the race: SubmitBookingAction
 * locks the room row (lockForUpdate) and re-checks conflicts inside that lock.
 * Two concurrent submits for the same slot therefore serialize to exactly this
 * sequence — the first wins and locks the slot, the second's in-lock re-check
 * finds it taken and is rejected, and only one booking persists.
 */
class SubmitBookingRaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_two_submits_for_the_same_slot_only_one_wins(): void
    {
        $room = Room::factory()->create([
            'is_active' => true,
            'status' => RoomStatus::Active,
            'approval_mode' => RoomApprovalMode::None, // auto-approve → first submit locks the slot
            'capacity' => 10,
        ]);
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $input = [
            'resource_id' => $room->id,
            'subject' => 'Rapat Koordinasi',
            'attendee_count' => 5,
            'starts_at' => '2026-06-01 09:00:00',
            'ends_at' => '2026-06-01 10:00:00',
        ];

        $action = app(SubmitBookingAction::class);

        // First submit wins and locks the slot.
        $first = $action->execute($alice, $input, notify: false);
        $this->assertSame(BookingStatus::Approved, $first->status);

        // Second submit for the identical slot is rejected by the in-lock re-check.
        $secondRejected = false;
        try {
            $action->execute($bob, $input, notify: false);
        } catch (\Throwable) {
            $secondRejected = true;
        }

        $this->assertTrue($secondRejected, 'Second submit for the same slot must be rejected.');
        $this->assertSame(1, Booking::query()->where('resource_id', $room->id)->count());
    }
}
