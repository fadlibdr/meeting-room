<?php

declare(strict_types=1);

namespace Tests\Feature\FrontOffice;

use App\Livewire\FrontOffice\DailyCheckIn;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class DailyCheckInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-06-08 03:00:00'); // 10:00 WIB, Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $user->roles()->sync([Role::where('code', $roleCode)->firstOrFail()->id]);

        return $user;
    }

    private function approvedBookingAt(string $utcStart, ?string $subject = null): Booking
    {
        return Booking::factory()->approved()->create([
            'room_id' => Room::factory(),
            'subject' => $subject ?? 'Rapat '.fake()->unique()->bothify('####'),
            'starts_at' => $utcStart,
            'ends_at' => Carbon::parse($utcStart)->addHour(),
        ]);
    }

    public function test_front_office_role_has_check_in_permission(): void
    {
        $fo = $this->userWithRole('front_office');
        $this->assertTrue($fo->hasPermission('bookings.check-in'));
        $this->assertTrue($fo->hasPermission('bookings.view-all'));
    }

    public function test_front_office_can_open_the_daily_view(): void
    {
        $this->actingAs($this->userWithRole('front_office'))
            ->get(route('front-office.index'))
            ->assertOk();
    }

    public function test_requester_is_forbidden(): void
    {
        $this->actingAs($this->userWithRole('requester'))
            ->get(route('front-office.index'))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('front-office.index'))->assertRedirect(route('login'));
    }

    public function test_daily_view_lists_approved_bookings_for_the_date(): void
    {
        $today = $this->approvedBookingAt('2026-06-08 02:00:00', 'Rapat Hari Ini Unik'); // 09:00 WIB
        $other = $this->approvedBookingAt('2026-06-09 02:00:00', 'Rapat Esok Unik'); // next day

        Livewire::actingAs($this->userWithRole('front_office'))
            ->test(DailyCheckIn::class)
            ->set('date', '2026-06-08')
            ->assertSee($today->subject)
            ->assertDontSee($other->subject);
    }

    public function test_check_in_records_timestamp_and_logs_activity(): void
    {
        $booking = $this->approvedBookingAt('2026-06-08 02:00:00');

        Livewire::actingAs($this->userWithRole('front_office'))
            ->test(DailyCheckIn::class)
            ->set('date', '2026-06-08')
            ->call('checkIn', $booking->id)
            ->assertHasNoErrors();

        $this->assertNotNull($booking->refresh()->checked_in_at);
        $this->assertDatabaseHas('activity_logs', [
            'module' => 'bookings',
            'event' => 'check-in',
        ]);
    }

    public function test_check_in_is_idempotent(): void
    {
        $booking = $this->approvedBookingAt('2026-06-08 02:00:00');
        $booking->forceFill(['checked_in_at' => Carbon::parse('2026-06-08 02:30:00')])->save();
        $original = $booking->refresh()->checked_in_at;

        Livewire::actingAs($this->userWithRole('front_office'))
            ->test(DailyCheckIn::class)
            ->call('checkIn', $booking->id);

        $this->assertTrue($original->equalTo($booking->refresh()->checked_in_at));
    }

    public function test_undo_check_in_clears_timestamp(): void
    {
        $booking = $this->approvedBookingAt('2026-06-08 02:00:00');
        $booking->forceFill(['checked_in_at' => now()])->save();

        Livewire::actingAs($this->userWithRole('front_office'))
            ->test(DailyCheckIn::class)
            ->call('undoCheckIn', $booking->id);

        $this->assertNull($booking->refresh()->checked_in_at);
    }

    public function test_cannot_check_in_a_non_approved_booking(): void
    {
        $booking = Booking::factory()->submitted()->create(['room_id' => Room::factory()]);

        Livewire::actingAs($this->userWithRole('front_office'))
            ->test(DailyCheckIn::class)
            ->call('checkIn', $booking->id)
            ->assertForbidden();

        $this->assertNull($booking->refresh()->checked_in_at);
    }

    public function test_ga_admin_can_also_check_in(): void
    {
        $booking = $this->approvedBookingAt('2026-06-08 02:00:00');

        Livewire::actingAs($this->userWithRole('ga_admin'))
            ->test(DailyCheckIn::class)
            ->call('checkIn', $booking->id)
            ->assertHasNoErrors();

        $this->assertNotNull($booking->refresh()->checked_in_at);
    }
}
