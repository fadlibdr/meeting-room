<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Booking;

use App\Livewire\Booking\BookingCalendar;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

class BookingCalendarTest extends TestCase
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

    // ─── AUTHORIZATION ──────────────────────────────────────────────

    public function test_requester_can_render_calendar(): void
    {
        $user = $this->userWithRole('requester');

        Livewire::actingAs($user)
            ->test(BookingCalendar::class)
            ->assertOk()
            ->assertSee('Kalender Reservasi');
    }

    public function test_unauthenticated_calendar_redirects_to_login(): void
    {
        $this->get('/calendar')->assertRedirect('/login');
    }

    // ─── DATE NAVIGATION ────────────────────────────────────────────

    public function test_next_day_advances_selected_date(): void
    {
        $user = $this->userWithRole('requester');
        $today = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');
        $tomorrow = CarbonImmutable::now('Asia/Jakarta')->addDay()->format('Y-m-d');

        Livewire::actingAs($user)
            ->test(BookingCalendar::class)
            ->assertSet('selectedDate', $today)
            ->call('nextDay')
            ->assertSet('selectedDate', $tomorrow);
    }

    public function test_set_today_resets_to_current_date(): void
    {
        $user = $this->userWithRole('requester');
        $today = CarbonImmutable::now('Asia/Jakarta')->format('Y-m-d');

        Livewire::actingAs($user)
            ->test(BookingCalendar::class)
            ->set('selectedDate', '2026-01-01')
            ->call('setToday')
            ->assertSet('selectedDate', $today);
    }

    // ─── ROOM FILTER ────────────────────────────────────────────────

    public function test_toggle_room_adds_then_removes_from_filter(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();

        Livewire::actingAs($user)
            ->test(BookingCalendar::class)
            ->assertSet('roomFilterIds', [])
            ->call('toggleRoom', $room->id)
            ->assertSet('roomFilterIds', [$room->id])
            ->call('toggleRoom', $room->id)
            ->assertSet('roomFilterIds', []);
    }

    // ─── COMPUTED STATE ─────────────────────────────────────────────

    public function test_bookings_query_excludes_non_locking_statuses(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();
        $monday = CarbonImmutable::parse('next monday', 'Asia/Jakarta')->format('Y-m-d');

        // Approved (locking) — should appear
        $approved = $this->createBooking($room, "{$monday} 10:00:00", "{$monday} 11:00:00", 'approved');
        // Cancelled (not locking) — should NOT appear
        $cancelled = $this->createBooking($room, "{$monday} 14:00:00", "{$monday} 15:00:00", 'cancelled');

        $component = Livewire::actingAs($user)
            ->test(BookingCalendar::class)
            ->set('selectedDate', $monday);

        $bookingIds = collect($component->viewData('bookings'))->pluck('id')->all();

        $this->assertContains($approved->id, $bookingIds);
        $this->assertNotContains($cancelled->id, $bookingIds);
    }
}
