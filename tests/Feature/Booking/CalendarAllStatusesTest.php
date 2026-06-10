<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Livewire\Booking\BookingCalendar;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

class CalendarAllStatusesTest extends TestCase
{
    use CreatesBookingScenarios;
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

    public function test_completed_bookings_now_show_on_the_calendar(): void
    {
        $room = $this->createRoomWithStandardHours();
        $completed = $this->createBooking($room, '2026-06-15 02:00:00', '2026-06-15 03:00:00', 'completed');

        $ids = collect(
            Livewire::actingAs($this->requester())
                ->test(BookingCalendar::class)
                ->set('selectedDate', '2026-06-15')
                ->viewData('bookings')
        )->pluck('id')->all();

        $this->assertContains($completed->id, $ids);
    }

    public function test_cancelled_and_rejected_bookings_are_excluded(): void
    {
        $room = $this->createRoomWithStandardHours();
        $cancelled = $this->createBooking($room, '2026-06-15 04:00:00', '2026-06-15 05:00:00', 'cancelled');
        $rejected = $this->createBooking($room, '2026-06-15 06:00:00', '2026-06-15 07:00:00', 'rejected');
        $approved = $this->createBooking($room, '2026-06-15 08:00:00', '2026-06-15 09:00:00', 'approved');

        $ids = collect(
            Livewire::actingAs($this->requester())
                ->test(BookingCalendar::class)
                ->set('selectedDate', '2026-06-15')
                ->viewData('bookings')
        )->pluck('id')->all();

        $this->assertContains($approved->id, $ids);
        $this->assertNotContains($cancelled->id, $ids);
        $this->assertNotContains($rejected->id, $ids);
    }
}
