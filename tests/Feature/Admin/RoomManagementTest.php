<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\RoomStatus;
use App\Livewire\Admin\RoomForm;
use App\Livewire\Admin\RoomList;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomManagementTest extends TestCase
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

    // ---- Route access: management UI gated by rooms.update ----

    public function test_super_admin_can_view_room_index(): void
    {
        $this->actingAs($this->userWithRole('super_admin'))->get(route('admin.rooms.index'))->assertOk();
    }

    public function test_room_index_is_translated_to_english(): void
    {
        $admin = $this->userWithRole('super_admin');
        $admin->update(['locale' => 'en']);

        $this->actingAs($admin)->get(route('admin.rooms.index'))
            ->assertOk()
            ->assertSee('Manage Rooms')
            ->assertSee('Approval Mode')
            ->assertDontSee('Kelola Ruangan');
    }

    public function test_ga_admin_can_view_room_index(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'))->get(route('admin.rooms.index'))->assertOk();
    }

    public function test_requester_cannot_view_room_index(): void
    {
        $this->actingAs($this->userWithRole('requester'))->get(route('admin.rooms.index'))->assertForbidden();
    }

    public function test_unit_approver_cannot_view_room_index(): void
    {
        $this->actingAs($this->userWithRole('unit_approver'))->get(route('admin.rooms.index'))->assertForbidden();
    }

    public function test_admin_can_view_create_and_edit_screens(): void
    {
        $room = Room::factory()->create();
        $admin = $this->userWithRole('ga_admin');

        $this->actingAs($admin)->get(route('admin.rooms.create'))->assertOk();
        $this->actingAs($admin)->get(route('admin.rooms.edit', $room->id))->assertOk();
    }

    public function test_requester_cannot_view_create_screen(): void
    {
        $this->actingAs($this->userWithRole('requester'))->get(route('admin.rooms.create'))->assertForbidden();
    }

    // ---- Create ----

    public function test_admin_can_create_room(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomForm::class)
            ->set('code', 'RM-NEW1')
            ->set('name', 'Ruang Garuda 1')
            ->set('location', 'Gedung Utama')
            ->set('floor', 'Lantai 3')
            ->set('capacity', 12)
            ->set('status', RoomStatus::Active->value)
            ->set('approvalMode', 'unit_approver')
            ->set('bookingBufferMinutes', 15)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.rooms.index'));

        $room = Room::where('code', 'RM-NEW1')->first();
        $this->assertNotNull($room);
        $this->assertSame(RoomStatus::Active, $room->status);
        $this->assertTrue($room->is_active);
    }

    public function test_room_requires_code_name_and_capacity(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomForm::class)
            ->set('code', '')
            ->set('name', '')
            ->set('capacity', null)
            ->call('save')
            ->assertHasErrors(['code', 'name', 'capacity']);
    }

    public function test_room_code_must_be_unique(): void
    {
        Room::factory()->create(['code' => 'RM-DUP']);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomForm::class)
            ->set('code', 'RM-DUP')
            ->set('name', 'Ruang Lain')
            ->set('capacity', 8)
            ->set('status', RoomStatus::Active->value)
            ->set('approvalMode', 'none')
            ->call('save')
            ->assertHasErrors(['code']);
    }

    public function test_non_active_status_sets_is_active_false(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomForm::class)
            ->set('code', 'RM-INA1')
            ->set('name', 'Ruang Nonaktif')
            ->set('capacity', 6)
            ->set('status', RoomStatus::Inactive->value)
            ->set('approvalMode', 'none')
            ->call('save')
            ->assertHasNoErrors();

        $room = Room::where('code', 'RM-INA1')->firstOrFail();
        $this->assertSame(RoomStatus::Inactive, $room->status);
        $this->assertFalse($room->is_active);
    }

    // ---- Edit ----

    public function test_admin_can_update_room(): void
    {
        $room = Room::factory()->create(['name' => 'Nama Lama', 'capacity' => 8]);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomForm::class, ['room' => $room])
            ->assertSet('isEditMode', true)
            ->assertSet('name', 'Nama Lama')
            ->set('name', 'Nama Baru')
            ->set('capacity', 20)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.rooms.index'));

        $room->refresh();
        $this->assertSame('Nama Baru', $room->name);
        $this->assertSame(20, $room->capacity);
    }

    // ---- Status actions ----

    public function test_admin_can_deactivate_room(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Active, 'is_active' => true]);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomList::class)->call('deactivate', $room->id);

        $room->refresh();
        $this->assertSame(RoomStatus::Inactive, $room->status);
        $this->assertFalse($room->is_active);
    }

    public function test_admin_can_archive_and_reactivate_room(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomList::class)->call('archive', $room->id);
        $room->refresh();
        $this->assertSame(RoomStatus::Archived, $room->status);

        Livewire::test(RoomList::class)->call('activate', $room->id);
        $room->refresh();
        $this->assertSame(RoomStatus::Active, $room->status);
        $this->assertTrue($room->is_active);
    }

    public function test_deactivating_room_with_future_bookings_is_non_destructive(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Active, 'is_active' => true]);
        $requester = $this->userWithRole('requester');
        $booking = Booking::factory()->create([
            'resource_id' => $room->id,
            'requester_user_id' => $requester->id,
            'status' => BookingStatus::Approved,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHour(),
        ]);

        $this->actingAs($this->userWithRole('ga_admin'));
        Livewire::test(RoomList::class)->call('deactivate', $room->id);

        $room->refresh();
        $booking->refresh();
        $this->assertSame(RoomStatus::Inactive, $room->status);
        $this->assertSame(BookingStatus::Approved, $booking->status); // untouched (§2.3)
    }

    public function test_requester_cannot_deactivate_room(): void
    {
        $room = Room::factory()->create(['status' => RoomStatus::Active, 'is_active' => true]);
        $this->actingAs($this->userWithRole('requester'));

        Livewire::test(RoomList::class)->call('deactivate', $room->id)->assertForbidden();

        $room->refresh();
        $this->assertSame(RoomStatus::Active, $room->status); // unchanged
    }
}
