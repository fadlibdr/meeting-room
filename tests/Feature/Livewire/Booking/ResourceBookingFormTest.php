<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Booking;

use App\Enums\ResourceType;
use App\Livewire\Booking\BookingForm;
use App\Livewire\Booking\RoomAvailabilityPicker;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Stage 3 E2c — the end-user booking form can book non-room resources.
 */
class ResourceBookingFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
    }

    private function requester(): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', 'requester')->firstOrFail()->id]);

        return $user;
    }

    private function nextMondayAt(string $time): string
    {
        return CarbonImmutable::parse('next monday', 'UTC')->setTimeFromTimeString($time)->format('Y-m-d\TH:i');
    }

    public function test_user_can_book_a_non_room_resource_through_the_form(): void
    {
        $vehicle = Resource::factory()->ofType(ResourceType::Vehicle)->create(['capacity' => 7]);

        Livewire::actingAs($this->requester())
            ->test(BookingForm::class)
            ->set('resourceType', 'vehicle')
            ->set('roomId', (string) $vehicle->id)
            ->set('subject', 'Pinjam mobil dinas')
            ->set('attendeeCount', 3)
            ->set('startsAt', $this->nextMondayAt('10:00'))
            ->set('endsAt', $this->nextMondayAt('11:00'))
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('calendar.index'));

        $this->assertDatabaseHas('bookings', [
            'resource_id' => $vehicle->id,
            'subject' => 'Pinjam mobil dinas',
        ]);
    }

    public function test_room_id_must_match_the_selected_resource_type(): void
    {
        $room = Room::factory()->create();

        // Selecting an equipment type but passing a room id → validation fails.
        Livewire::actingAs($this->requester())
            ->test(BookingForm::class)
            ->set('resourceType', 'equipment')
            ->set('roomId', (string) $room->id)
            ->set('subject', 'Mismatch')
            ->set('attendeeCount', 1)
            ->set('startsAt', $this->nextMondayAt('10:00'))
            ->set('endsAt', $this->nextMondayAt('11:00'))
            ->call('submit')
            ->assertHasErrors(['roomId']);
    }

    public function test_switching_resource_type_clears_the_current_selection(): void
    {
        $room = Room::factory()->create();

        Livewire::actingAs($this->requester())
            ->test(BookingForm::class)
            ->set('roomId', (string) $room->id)
            ->set('resourceType', 'desk')
            ->assertSet('roomId', '')
            ->assertSet('conflictStatus', 'unknown');
    }

    public function test_picker_lists_only_the_selected_resource_type(): void
    {
        Room::factory()->create(['name' => 'Ruang Tersembunyi']);
        Resource::factory()->ofType(ResourceType::Vehicle)->create(['name' => 'Avanza Dinas']);

        Livewire::actingAs($this->requester())
            ->test(RoomAvailabilityPicker::class, ['resourceType' => 'vehicle'])
            ->assertSee('Avanza Dinas')
            ->assertDontSee('Ruang Tersembunyi');
    }
}
