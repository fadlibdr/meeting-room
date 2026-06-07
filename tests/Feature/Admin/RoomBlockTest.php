<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\BookingStatus;
use App\Enums\RoomBlockType;
use App\Livewire\Admin\RoomBlockForm;
use App\Livewire\Admin\RoomBlockList;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class RoomBlockTest extends TestCase
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

    private function overlappingApprovedBooking(Room $room, string $subject = 'Rapat'): Booking
    {
        return Booking::factory()->create([
            'resource_id' => $room->id,
            'subject' => $subject,
            'status' => BookingStatus::Approved,
            'starts_at' => Carbon::parse('2026-06-01 10:00:00'),
            'ends_at' => Carbon::parse('2026-06-01 11:00:00'),
        ]);
    }

    public function test_admin_can_view_block_index(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'))->get(route('admin.room-blocks.index'))->assertOk();
    }

    public function test_requester_cannot_view_block_index(): void
    {
        $this->actingAs($this->userWithRole('requester'))->get(route('admin.room-blocks.index'))->assertForbidden();
    }

    public function test_admin_can_view_create_screen(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'))->get(route('admin.room-blocks.create'))->assertOk();
    }

    public function test_requester_cannot_view_create_screen(): void
    {
        $this->actingAs($this->userWithRole('requester'))->get(route('admin.room-blocks.create'))->assertForbidden();
    }

    public function test_admin_can_create_a_block(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomBlockForm::class)
            ->set('roomId', $room->id)
            ->set('blockType', 'maintenance')
            ->set('title', 'Pemeliharaan AC')
            ->set('startsAt', '2026-06-01T09:00')
            ->set('endsAt', '2026-06-01T12:00')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.room-blocks.index'));

        $block = RoomBlockSchedule::where('room_id', $room->id)->firstOrFail();
        $this->assertSame('Pemeliharaan AC', $block->title);
        $this->assertSame(RoomBlockType::Maintenance, $block->block_type);
        $this->assertTrue($block->is_active);
    }

    public function test_block_requires_room_title_and_times(): void
    {
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomBlockForm::class)
            ->set('title', '')
            ->call('save')
            ->assertHasErrors(['roomId', 'title', 'startsAt', 'endsAt']);
    }

    public function test_block_rejects_end_before_start(): void
    {
        $room = Room::factory()->create();
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomBlockForm::class)
            ->set('roomId', $room->id)
            ->set('title', 'Pemeliharaan')
            ->set('startsAt', '2026-06-01T12:00')
            ->set('endsAt', '2026-06-01T09:00')
            ->call('save')
            ->assertHasErrors(['endsAt']);
    }

    public function test_creating_block_with_conflict_requires_acknowledgement(): void
    {
        $room = Room::factory()->create();
        $booking = $this->overlappingApprovedBooking($room);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomBlockForm::class)
            ->set('roomId', $room->id)
            ->set('title', 'Pemeliharaan')
            ->set('startsAt', '2026-06-01T09:00')
            ->set('endsAt', '2026-06-01T12:00')
            ->call('save')
            ->assertHasErrors(['conflict']);

        $this->assertSame(0, RoomBlockSchedule::count());
        $this->assertSame(BookingStatus::Approved, $booking->refresh()->status);
    }

    public function test_force_create_cancels_the_conflicting_booking(): void
    {
        $room = Room::factory()->create();
        $booking = $this->overlappingApprovedBooking($room);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomBlockForm::class)
            ->set('roomId', $room->id)
            ->set('title', 'Pemeliharaan')
            ->set('startsAt', '2026-06-01T09:00')
            ->set('endsAt', '2026-06-01T12:00')
            ->set('cancelConflicting', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.room-blocks.index'));

        $this->assertSame(1, RoomBlockSchedule::where('room_id', $room->id)->count());
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
    }

    public function test_live_preview_shows_conflicting_bookings(): void
    {
        $room = Room::factory()->create();
        $this->overlappingApprovedBooking($room, 'Rapat Penting');
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomBlockForm::class)
            ->set('roomId', $room->id)
            ->set('startsAt', '2026-06-01T09:00')
            ->set('endsAt', '2026-06-01T12:00')
            ->assertSee('Rapat Penting');
    }

    public function test_admin_can_cancel_a_block(): void
    {
        $block = RoomBlockSchedule::factory()->create(['is_active' => true, 'cancelled_at' => null]);
        $this->actingAs($this->userWithRole('ga_admin'));

        Livewire::test(RoomBlockList::class)->call('cancel', $block->id);

        $block->refresh();
        $this->assertFalse($block->is_active);
        $this->assertNotNull($block->cancelled_at);
    }

    public function test_requester_cannot_cancel_a_block(): void
    {
        $block = RoomBlockSchedule::factory()->create(['is_active' => true, 'cancelled_at' => null]);
        $this->actingAs($this->userWithRole('requester'));

        Livewire::test(RoomBlockList::class)->call('cancel', $block->id)->assertForbidden();

        $this->assertTrue($block->refresh()->is_active);
    }
}
