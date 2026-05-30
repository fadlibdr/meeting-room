<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Booking;

use App\Livewire\Booking\BookingForm;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

/**
 * Tests the dispatch contract between RoomAvailabilityPicker and BookingForm.
 *
 * Picker dispatches 'room-selected' on click; form's #[On('room-selected')]
 * handler updates roomId and triggers conflict re-check. These tests verify
 * the contract from the form side (events flow correctly into the listener).
 */
class PickerFormIntegrationTest extends TestCase
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

    // ─── EVENT FLOW ─────────────────────────────────────────────────

    public function test_room_selected_event_with_window_set_triggers_clear_conflict_status(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();

        $component = Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->set('startsAt', $this->nextMondayAt('10:00'))
            ->set('endsAt', $this->nextMondayAt('11:00'))
            ->dispatch('room-selected', roomId: $room->id);

        $component->assertSet('roomId', (string) $room->id);
        $component->assertSet('conflictStatus', 'clear');
    }

    public function test_room_selected_event_with_conflicting_booking_triggers_conflict_status(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();
        $monday = CarbonImmutable::parse('next monday', 'UTC')->format('Y-m-d');

        $this->createBooking($room, "{$monday} 10:00:00", "{$monday} 11:00:00", 'approved');

        $component = Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->set('startsAt', $this->nextMondayAt('10:30'))
            ->set('endsAt', $this->nextMondayAt('11:30'))
            ->dispatch('room-selected', roomId: $room->id);

        $component->assertSet('roomId', (string) $room->id);
        $component->assertSet('conflictStatus', 'conflict');
    }

    // ─── REGRESSION ─────────────────────────────────────────────────

    public function test_changing_time_after_room_selection_re_runs_conflict_check(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();
        $monday = CarbonImmutable::parse('next monday', 'UTC')->format('Y-m-d');

        // Existing booking 14:00-15:00
        $this->createBooking($room, "{$monday} 14:00:00", "{$monday} 15:00:00", 'approved');

        $component = Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->set('startsAt', $this->nextMondayAt('10:00'))
            ->set('endsAt', $this->nextMondayAt('11:00'))
            ->dispatch('room-selected', roomId: $room->id);

        // Initial state: clear (10:00-11:00 doesn't overlap 14:00-15:00)
        $component->assertSet('conflictStatus', 'clear');

        // Change time to overlap the existing booking
        $component
            ->set('startsAt', $this->nextMondayAt('14:30'))
            ->set('endsAt', $this->nextMondayAt('15:30'));

        // Should now be conflict
        $component->assertSet('conflictStatus', 'conflict');
    }
}
