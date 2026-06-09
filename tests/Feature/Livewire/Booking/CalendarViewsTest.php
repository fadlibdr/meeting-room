<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Booking;

use App\Livewire\Booking\BookingCalendar;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

class CalendarViewsTest extends TestCase
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

    public function test_default_view_is_day(): void
    {
        Livewire::actingAs($this->requester())
            ->test(BookingCalendar::class)
            ->assertSet('view', 'day');
    }

    public function test_set_view_switches_and_rejects_invalid(): void
    {
        Livewire::actingAs($this->requester())
            ->test(BookingCalendar::class)
            ->call('setView', 'week')->assertSet('view', 'week')
            ->call('setView', 'month')->assertSet('view', 'month')
            ->call('setView', 'bogus')->assertSet('view', 'month'); // unchanged
    }

    public function test_week_view_exposes_seven_days(): void
    {
        $component = Livewire::actingAs($this->requester())
            ->test(BookingCalendar::class)
            ->set('selectedDate', '2026-06-15') // a Monday
            ->call('setView', 'week');

        $this->assertCount(7, $component->viewData('weekDays'));
    }

    public function test_month_grid_is_rows_of_seven(): void
    {
        $component = Livewire::actingAs($this->requester())
            ->test(BookingCalendar::class)
            ->set('selectedDate', '2026-06-15')
            ->call('setView', 'month');

        $grid = $component->viewData('monthGrid');
        $this->assertNotEmpty($grid);
        foreach ($grid as $week) {
            $this->assertCount(7, $week);
        }
    }

    public function test_go_to_day_switches_to_day_view_on_that_date(): void
    {
        Livewire::actingAs($this->requester())
            ->test(BookingCalendar::class)
            ->call('setView', 'month')
            ->call('goToDay', '2026-06-17')
            ->assertSet('view', 'day')
            ->assertSet('selectedDate', '2026-06-17');
    }

    public function test_next_shifts_by_week_then_month(): void
    {
        Livewire::actingAs($this->requester())
            ->test(BookingCalendar::class)
            ->set('selectedDate', '2026-06-15')
            ->call('setView', 'week')
            ->call('next')->assertSet('selectedDate', '2026-06-22')      // +1 week
            ->call('setView', 'month')
            ->call('next')->assertSet('selectedDate', '2026-07-22');     // +1 month
    }

    public function test_week_view_loads_bookings_across_the_whole_week(): void
    {
        $room = $this->createRoomWithStandardHours();
        // Wednesday of the week of Mon 2026-06-15 (09:00–10:00 WIB = 02:00–03:00 UTC).
        $booking = $this->createBooking($room, '2026-06-17 02:00:00', '2026-06-17 03:00:00', 'approved');

        $component = Livewire::actingAs($this->requester())
            ->test(BookingCalendar::class)
            ->set('selectedDate', '2026-06-15') // Monday — a DAY view would miss Wednesday
            ->call('setView', 'week');

        $ids = collect($component->viewData('bookings'))->pluck('id')->all();
        $this->assertContains($booking->id, $ids);
    }

    public function test_week_and_month_views_render(): void
    {
        Livewire::actingAs($this->requester())
            ->test(BookingCalendar::class)
            ->set('selectedDate', '2026-06-15')
            ->call('setView', 'week')
            ->assertSee('Sen', false)   // Monday short name (id locale) — week day header
            ->call('setView', 'month')
            ->assertSee('Juni', false); // month range label (id locale)
    }
}
