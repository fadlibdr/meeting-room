<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Livewire\Admin\RoomFacilityManager;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomFacility;
use App\Models\RoomFacilityItem;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomFacilityAssignmentTest extends TestCase
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

    private function assign(Room $room, RoomFacility $facility, int $qty = 1): RoomFacilityItem
    {
        return RoomFacilityItem::create([
            'room_id' => $room->id,
            'room_facility_id' => $facility->id,
            'quantity' => $qty,
            'is_operational' => true,
        ]);
    }

    public function test_admin_can_assign_a_facility_to_a_room(): void
    {
        $room = Room::factory()->create();
        $facility = RoomFacility::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomFacilityManager::class, ['room' => $room])
            ->set('selectedFacilityId', $facility->id)
            ->set('quantity', 2)
            ->set('isOperational', true)
            ->set('notes', 'Di dinding depan')
            ->call('addFacility')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('room_facility_items', [
            'room_id' => $room->id,
            'room_facility_id' => $facility->id,
            'quantity' => 2,
            'is_operational' => true,
            'notes' => 'Di dinding depan',
        ]);
    }

    public function test_cannot_assign_the_same_facility_twice(): void
    {
        $room = Room::factory()->create();
        $facility = RoomFacility::factory()->create(['is_active' => true]);
        $this->assign($room, $facility);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomFacilityManager::class, ['room' => $room])
            ->set('selectedFacilityId', $facility->id)
            ->set('quantity', 1)
            ->call('addFacility')
            ->assertHasErrors(['selectedFacilityId']);

        $this->assertSame(1, RoomFacilityItem::where('room_id', $room->id)->count());
    }

    public function test_cannot_assign_an_inactive_facility(): void
    {
        $room = Room::factory()->create();
        $facility = RoomFacility::factory()->create(['is_active' => false]);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomFacilityManager::class, ['room' => $room])
            ->set('selectedFacilityId', $facility->id)
            ->set('quantity', 1)
            ->call('addFacility')
            ->assertHasErrors(['selectedFacilityId']);
    }

    public function test_quantity_must_be_at_least_one(): void
    {
        $room = Room::factory()->create();
        $facility = RoomFacility::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomFacilityManager::class, ['room' => $room])
            ->set('selectedFacilityId', $facility->id)
            ->set('quantity', 0)
            ->call('addFacility')
            ->assertHasErrors(['quantity']);
    }

    public function test_admin_can_update_an_assignment(): void
    {
        $room = Room::factory()->create();
        $facility = RoomFacility::factory()->create(['is_active' => true]);
        $item = $this->assign($room, $facility);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomFacilityManager::class, ['room' => $room])
            ->call('startEdit', $item->id)
            ->assertSet('editQuantity', 1)
            ->set('editQuantity', 5)
            ->set('editIsOperational', false)
            ->set('editNotes', 'Sedang diperbaiki')
            ->call('saveEdit')
            ->assertHasNoErrors();

        $item->refresh();
        $this->assertSame(5, $item->quantity);
        $this->assertFalse($item->is_operational);
        $this->assertSame('Sedang diperbaiki', $item->notes);
    }

    public function test_admin_can_remove_an_assignment(): void
    {
        $room = Room::factory()->create();
        $facility = RoomFacility::factory()->create(['is_active' => true]);
        $item = $this->assign($room, $facility);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomFacilityManager::class, ['room' => $room])->call('remove', $item->id);

        $this->assertDatabaseMissing('room_facility_items', ['id' => $item->id]);
    }

    public function test_requester_cannot_assign_a_facility(): void
    {
        $room = Room::factory()->create();
        $facility = RoomFacility::factory()->create(['is_active' => true]);
        $this->actingAs($this->userWithRole('requester'));

        Livewire::test(RoomFacilityManager::class, ['room' => $room])
            ->set('selectedFacilityId', $facility->id)
            ->set('quantity', 1)
            ->call('addFacility')
            ->assertForbidden();

        $this->assertDatabaseMissing('room_facility_items', [
            'room_id' => $room->id,
            'room_facility_id' => $facility->id,
        ]);
    }
}
