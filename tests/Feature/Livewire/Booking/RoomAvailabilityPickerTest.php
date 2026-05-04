<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Booking;

use App\Livewire\Booking\RoomAvailabilityPicker;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

class RoomAvailabilityPickerTest extends TestCase
{
    use CreatesBookingScenarios;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function nextMondayAt(string $time): string
    {
        return CarbonImmutable::parse('next monday', 'UTC')
            ->setTimeFromTimeString($time)
            ->format('Y-m-d\TH:i');
    }

    // ─── INITIAL STATE ──────────────────────────────────────────────

    public function test_loads_active_rooms_only(): void
    {
        $user = $this->userWithRole('requester');
        $activeRoom = $this->createRoomWithStandardHours();
        $inactiveRoom = Room::factory()->create(['is_active' => false]);

        $component = Livewire::actingAs($user)
            ->test(RoomAvailabilityPicker::class);

        $roomIds = $component->viewData('rooms')->pluck('id')->all();

        $this->assertContains($activeRoom->id, $roomIds);
        $this->assertNotContains($inactiveRoom->id, $roomIds);
    }

    public function test_availability_is_unknown_when_window_unset(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();

        $component = Livewire::actingAs($user)
            ->test(RoomAvailabilityPicker::class);

        $availability = $component->viewData('availability');

        $this->assertEquals('unknown', $availability[$room->id]['status']);
    }

    // ─── AVAILABILITY MAP ───────────────────────────────────────────

    public function test_room_marked_available_when_no_conflicts_in_window(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();

        $component = Livewire::actingAs($user)
            ->test(RoomAvailabilityPicker::class, [
                'startsAt' => $this->nextMondayAt('10:00'),
                'endsAt' => $this->nextMondayAt('11:00'),
            ]);

        $availability = $component->viewData('availability');

        $this->assertEquals('available', $availability[$room->id]['status']);
        $this->assertNull($availability[$room->id]['conflictTitle']);
    }

    public function test_room_marked_unavailable_when_existing_booking_overlaps(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();
        $monday = CarbonImmutable::parse('next monday', 'UTC')->format('Y-m-d');

        $this->createBooking($room, "{$monday} 10:00:00", "{$monday} 11:00:00", 'approved');

        $component = Livewire::actingAs($user)
            ->test(RoomAvailabilityPicker::class, [
                'startsAt' => $this->nextMondayAt('10:30'),
                'endsAt' => $this->nextMondayAt('11:30'),
            ]);

        $availability = $component->viewData('availability');

        $this->assertEquals('unavailable', $availability[$room->id]['status']);
        $this->assertNotNull($availability[$room->id]['conflictTitle']);
    }

    // ─── CAPACITY ADVISORY ──────────────────────────────────────────

    public function test_exceeds_capacity_flag_set_when_attendee_count_too_high(): void
    {
        $user = $this->userWithRole('requester');
        $room = Room::factory()->create(['capacity' => 4, 'is_active' => true, 'status' => 'active']);
        $this->createRoomHoursForRoom($room);

        $component = Livewire::actingAs($user)
            ->test(RoomAvailabilityPicker::class, [
                'attendeeCount' => 10,
            ]);

        $availability = $component->viewData('availability');

        $this->assertTrue($availability[$room->id]['exceedsCapacity']);
    }

    // ─── INTERACTION ────────────────────────────────────────────────

    public function test_select_room_dispatches_event_with_room_id(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();

        Livewire::actingAs($user)
            ->test(RoomAvailabilityPicker::class)
            ->call('selectRoom', $room->id)
            ->assertDispatched('room-selected', roomId: $room->id);
    }

    /**
     * Helper for capacity test: create operating hours for an existing room
     * (since createRoomWithStandardHours bundles room+hours creation).
     */
    private function createRoomHoursForRoom(Room $room): void
    {
        for ($day = 1; $day <= 5; $day++) {
            RoomOperatingHour::factory()->create([
                'room_id' => $room->id,
                'day_of_week' => $day,
                'open_time' => '08:00:00',
                'close_time' => '17:00:00',
                'is_closed' => false,
            ]);
        }
    }
}
