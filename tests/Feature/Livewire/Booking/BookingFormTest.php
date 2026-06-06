<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Booking;

use App\Enums\RoomApprovalMode;
use App\Livewire\Booking\BookingForm;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\CreatesBookingScenarios;
use Tests\TestCase;

class BookingFormTest extends TestCase
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

    /**
     * Get next Monday at given time as 'YYYY-MM-DDTHH:MM' (datetime-local format).
     * Used to ensure tests run within standard operating hours (Mon-Fri 08:00-17:00).
     */
    private function nextMondayAt(string $time): string
    {
        return CarbonImmutable::parse('next monday', 'UTC')
            ->setTimeFromTimeString($time)
            ->format('Y-m-d\TH:i');
    }

    // ─── AUTHORIZATION ──────────────────────────────────────────────

    public function test_requester_can_render_booking_form(): void
    {
        $user = $this->userWithRole('requester');

        Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->assertOk()
            ->assertSee('Judul Rapat');
    }

    public function test_time_inputs_force_24_hour_format(): void
    {
        $user = $this->userWithRole('requester');

        // lang="id" on the datetime-local inputs forces a 24-hour picker in
        // Chromium regardless of the UI language.
        Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->assertOk()
            ->assertSeeHtml('type="datetime-local" lang="id"');
    }

    public function test_unauthenticated_route_redirects_to_login(): void
    {
        $this->get('/bookings/new')->assertRedirect('/login');
    }

    // ─── VALIDATION ─────────────────────────────────────────────────

    public function test_submit_with_empty_fields_shows_indonesian_errors(): void
    {
        // Note: attendeeCount is omitted from expected errors because the property
        // defaults to 1 (sensible UX default), which passes min:1 validation.
        // Required-validation coverage for attendeeCount lives in StoreBookingRequest tests.
        $user = $this->userWithRole('requester');

        Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->call('submit')
            ->assertHasErrors(['roomId', 'subject', 'startsAt', 'endsAt']);
    }

    public function test_submit_with_starts_at_in_past_fails_validation(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();

        Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->set('roomId', (string) $room->id)
            ->set('subject', 'Test rapat')
            ->set('attendeeCount', 5)
            ->set('startsAt', '2020-01-01T10:00')
            ->set('endsAt', '2020-01-01T11:00')
            ->call('submit')
            ->assertHasErrors(['startsAt']);
    }

    // ─── SUBMIT FLOW ────────────────────────────────────────────────
    public function test_submit_happy_path_creates_booking_and_redirects(): void
    {
        $user = $this->userWithRole('requester');
        // Force approval_mode=none so submit auto-approves. RoomFactory's default
        // is randomElement across [none, unit_approver, ga_admin]; without this
        // override the test fails ~67% of runs (factory rolls a mode that requires
        // routing to an approver who doesn't exist in this test setup).
        // Approval-routing happy paths have their own dedicated tests.
        $room = $this->createRoomWithStandardHours();
        $room->update(['approval_mode' => RoomApprovalMode::None]);

        $startsAt = $this->nextMondayAt('10:00');
        $endsAt = $this->nextMondayAt('11:00');

        Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->set('roomId', (string) $room->id)
            ->set('subject', 'Rapat Test M1-G')
            ->set('attendeeCount', 5)
            ->set('startsAt', $startsAt)
            ->set('endsAt', $endsAt)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertRedirect(route('calendar.index'));

        $this->assertDatabaseHas('bookings', [
            'room_id' => $room->id,
            'requester_user_id' => $user->id,
            'subject' => 'Rapat Test M1-G',
            'attendee_count' => 5,
        ]);
    }

    public function test_submit_with_conflict_shows_banner_and_keeps_fields(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();

        // Pre-existing approved booking 10:00-11:00 next Monday
        $monday = CarbonImmutable::parse('next monday', 'UTC')->format('Y-m-d');
        $this->createBooking($room, "{$monday} 10:00:00", "{$monday} 11:00:00", 'approved');

        // Try to book overlapping slot 10:30-11:30
        $component = Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->set('roomId', (string) $room->id)
            ->set('subject', 'Bentrok Test')
            ->set('attendeeCount', 3)
            ->set('startsAt', $this->nextMondayAt('10:30'))
            ->set('endsAt', $this->nextMondayAt('11:30'))
            ->call('submit');

        // Banner shown, no redirect, fields populated
        $component
            ->assertNoRedirect()
            ->assertSet('subject', 'Bentrok Test')
            ->assertSet('attendeeCount', 3);

        $this->assertNotNull($component->get('submitError'));
        $this->assertStringContainsString('bentrok', strtolower((string) $component->get('submitError')));
    }

    public function test_url_pre_fill_sets_room_id_and_starts_at(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();
        $startsAt = $this->nextMondayAt('14:00');

        Livewire::actingAs($user)
            ->withQueryParams([
                'room_id' => (string) $room->id,
                'starts_at' => $startsAt,
            ])
            ->test(BookingForm::class)
            ->assertSet('roomId', (string) $room->id)
            ->assertSet('startsAt', $startsAt);
    }

    // ─── DISPATCH HANDLER ───────────────────────────────────────────

    public function test_room_selected_event_updates_room_id(): void
    {
        $user = $this->userWithRole('requester');
        $room = $this->createRoomWithStandardHours();

        Livewire::actingAs($user)
            ->test(BookingForm::class)
            ->dispatch('room-selected', roomId: $room->id)
            ->assertSet('roomId', (string) $room->id);
    }
}
