<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Booking;

use App\Enums\BookingStatus;
use App\Livewire\Booking\BookingForm;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature tests for BookingForm edit mode (M3-C-ii).
 *
 * Covers the wiring: the bookings/{booking}/edit route, mount() prefill
 * and authorization, submit() routing to UpdateBookingAction, and the
 * show-page Edit link. The UpdateBookingAction mechanics themselves
 * (pointer clearing, approval-row cancel, history, audit) are owned by
 * Tests\Unit\Actions\UpdateBookingActionTest.
 *
 * @see BookingForm
 */
class EditBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-05-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function attachRole(User $user, string $roleCode): User
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);

        return $user->fresh();
    }

    private function makeRequester(): User
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);

        return $this->attachRole($user, 'requester');
    }

    private function makeBooking(
        BookingStatus $status,
        User $owner,
        ?Room $room = null,
        string $startsAt = '2026-05-06 10:00:00',
        string $endsAt = '2026-05-06 11:00:00',
    ): Booking {
        $room ??= Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 20,
            'booking_buffer_minutes' => 0,
        ]);

        $attributes = [
            'booking_code' => 'BKG-EDIT-'.strtoupper($status->value).'-'.str_pad((string) $room->id, 4, '0', STR_PAD_LEFT),
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Awal',
            'agenda' => 'Agenda awal.',
            'attendee_count' => 4,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $status->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
        ];

        if ($status === BookingStatus::Submitted) {
            $approver = User::factory()->create(['is_active' => true]);
            $attributes['submitted_at'] = Carbon::now();
            $attributes['current_approval_step'] = 1;
            $attributes['current_approver_user_id'] = $approver->id;
            $booking = Booking::create($attributes);
            BookingApproval::create([
                'booking_id' => $booking->id,
                'sequence_no' => 1,
                'approver_user_id' => $approver->id,
                'status' => 'pending',
            ]);

            return $booking;
        }

        if ($status === BookingStatus::Approved) {
            $approver = User::factory()->create(['is_active' => true]);
            $attributes['submitted_at'] = Carbon::now()->subHour();
            $attributes['approved_at'] = Carbon::now();
            $booking = Booking::create($attributes);
            BookingApproval::create([
                'booking_id' => $booking->id,
                'sequence_no' => 1,
                'approver_user_id' => $approver->id,
                'status' => 'approved',
                'action_at' => Carbon::now(),
                'acted_by_user_id' => $approver->id,
            ]);

            return $booking;
        }

        return Booking::create($attributes);
    }

    // ─── RENDER & PREFILL ────────────────────────────────────────────

    public function test_owner_can_render_the_edit_form(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);

        $this->actingAs($owner);

        Livewire::test(BookingForm::class, ['booking' => $booking])
            ->assertOk();
    }

    public function test_edit_form_is_prefilled_from_the_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);

        $this->actingAs($owner);

        Livewire::test(BookingForm::class, ['booking' => $booking])
            ->assertSet('bookingId', $booking->id)
            ->assertSet('roomId', (string) $booking->resource_id)
            ->assertSet('subject', 'Rapat Awal')
            ->assertSet('agenda', 'Agenda awal.')
            ->assertSet('attendeeCount', 4)
            ->assertSet('startsAt', '2026-05-06T10:00');
    }

    // ─── SAVE ────────────────────────────────────────────────────────

    public function test_saving_an_edited_draft_updates_it_and_redirects_to_show(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);

        $this->actingAs($owner);

        Livewire::test(BookingForm::class, ['booking' => $booking])
            ->set('subject', 'Judul Diperbarui')
            ->call('submit')
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'subject' => 'Judul Diperbarui',
            'status' => BookingStatus::Draft->value,
        ]);
    }

    public function test_saving_an_edited_submitted_booking_reverts_it_to_draft(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Submitted, $owner);

        $this->actingAs($owner);

        Livewire::test(BookingForm::class, ['booking' => $booking])
            ->set('subject', 'Judul Diperbarui')
            ->call('submit')
            ->assertRedirect(route('bookings.show', $booking));

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Draft->value,
        ]);
    }

    public function test_edit_submit_with_a_conflict_shows_the_banner_and_does_not_save(): void
    {
        $owner = $this->makeRequester();
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 20,
            'booking_buffer_minutes' => 0,
        ]);
        $booking = $this->makeBooking(BookingStatus::Draft, $owner, $room);

        // An approved booking already occupies 14:00-15:00 in the same room.
        $this->makeBooking(
            BookingStatus::Approved,
            $this->makeRequester(),
            $room,
            '2026-05-06 14:00:00',
            '2026-05-06 15:00:00',
        );

        $this->actingAs($owner);

        Livewire::test(BookingForm::class, ['booking' => $booking])
            ->set('startsAt', '2026-05-06T14:00')
            ->set('endsAt', '2026-05-06T15:00')
            ->call('submit')
            ->assertNoRedirect()
            ->assertSee('bentrok');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'subject' => 'Rapat Awal',
        ]);
    }

    // ─── ROUTE AUTHORIZATION ─────────────────────────────────────────

    public function test_non_owner_is_forbidden_from_the_edit_route(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);
        $stranger = $this->makeRequester();

        $this->actingAs($stranger)
            ->get(route('bookings.edit', $booking))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_the_edit_route(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);

        $this->get(route('bookings.edit', $booking))
            ->assertRedirect(route('login'));
    }

    // ─── SHOW-PAGE ENTRY POINT ───────────────────────────────────────

    public function test_show_page_offers_an_edit_link_for_an_editable_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Draft, $owner);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertSee(route('bookings.edit', $booking));
    }

    public function test_show_page_hides_the_edit_link_for_an_approved_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeBooking(BookingStatus::Approved, $owner);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertDontSee(route('bookings.edit', $booking));
    }
}
