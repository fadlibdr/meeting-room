<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RoomOperatingHoursManager;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomOperatingHour;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomOperatingHoursTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function hoursFor(Room $room, int $day): RoomOperatingHour
    {
        return RoomOperatingHour::query()
            ->where('room_id', $room->id)
            ->where('day_of_week', $day)
            ->firstOrFail();
    }

    public function test_admin_can_save_operating_hours(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomOperatingHoursManager::class, ['room' => $room])
            ->set('isClosed.1', false)
            ->set('openTime.1', '09:30')
            ->set('closeTime.1', '16:00')
            ->call('save')
            ->assertHasNoErrors();

        $monday = $this->hoursFor($room, 1);
        // Stored as "HH:MM:SS" — the exact format BookingConflictService compares.
        $this->assertSame('09:30:00', $monday->open_time);
        $this->assertSame('16:00:00', $monday->close_time);
        $this->assertFalse($monday->is_closed);
    }

    public function test_saving_writes_a_row_for_every_day(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomOperatingHoursManager::class, ['room' => $room])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(7, RoomOperatingHour::where('room_id', $room->id)->count());
    }

    public function test_day_of_week_follows_carbon_convention(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        // Defaults: weekdays (Mon=1..Fri=5) open, weekend (Sat=6, Sun=0) closed.
        Livewire::test(RoomOperatingHoursManager::class, ['room' => $room])
            ->call('save')
            ->assertHasNoErrors();

        $monday = $this->hoursFor($room, 1);
        $this->assertFalse($monday->is_closed);
        $this->assertSame('08:00:00', $monday->open_time);

        $sunday = $this->hoursFor($room, 0);
        $this->assertTrue($sunday->is_closed);
        $this->assertNull($sunday->open_time);

        $this->assertTrue($this->hoursFor($room, 6)->is_closed);
    }

    public function test_closed_day_stores_null_times(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomOperatingHoursManager::class, ['room' => $room])
            ->set('isClosed.1', true)
            ->call('save')
            ->assertHasNoErrors();

        $monday = $this->hoursFor($room, 1);
        $this->assertTrue($monday->is_closed);
        $this->assertNull($monday->open_time);
        $this->assertNull($monday->close_time);
    }

    public function test_open_day_requires_open_and_close_times(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomOperatingHoursManager::class, ['room' => $room])
            ->set('isClosed.1', false)
            ->set('openTime.1', '')
            ->set('closeTime.1', '')
            ->call('save')
            ->assertHasErrors(['openTime.1', 'closeTime.1']);
    }

    public function test_close_must_be_after_open(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomOperatingHoursManager::class, ['room' => $room])
            ->set('isClosed.1', false)
            ->set('openTime.1', '17:00')
            ->set('closeTime.1', '08:00')
            ->call('save')
            ->assertHasErrors(['closeTime.1']);

        // All-or-nothing: a single bad day aborts the whole save.
        $this->assertSame(0, RoomOperatingHour::where('room_id', $room->id)->count());
    }

    public function test_editing_existing_hours_updates_in_place(): void
    {
        $room = Room::factory()->create();
        RoomOperatingHour::create([
            'room_id' => $room->id,
            'day_of_week' => 1,
            'open_time' => '08:00:00',
            'close_time' => '17:00:00',
            'is_closed' => false,
        ]);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomOperatingHoursManager::class, ['room' => $room])
            ->assertSet('openTime.1', '08:00')
            ->set('openTime.1', '10:00')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('10:00:00', $this->hoursFor($room, 1)->open_time);
        // updateOrCreate must not duplicate the Monday row.
        $this->assertSame(1, RoomOperatingHour::where('room_id', $room->id)->where('day_of_week', 1)->count());
    }

    public function test_requester_cannot_save_operating_hours(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('requester'));

        Livewire::test(RoomOperatingHoursManager::class, ['room' => $room])
            ->call('save')
            ->assertForbidden();

        $this->assertSame(0, RoomOperatingHour::where('room_id', $room->id)->count());
    }
}
