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
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature tests for BookingForm reschedule mode (M3-E-ii).
 *
 * Covers the wiring: the bookings/{booking}/reschedule route, mount()
 * prefill + reschedule authorization, and submit() routing to
 * RescheduleBookingAction. The action mechanics (atomic cancel + resubmit,
 * the audit link, notification suppression) are owned by
 * Tests\Unit\Actions\RescheduleBookingActionTest.
 *
 * @see BookingForm
 */
class RescheduleBookingTest extends TestCase
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

    private function makeRoom(string $approvalMode = 'unit_approver'): Room
    {
        return Room::factory()->create([
            'approval_mode' => $approvalMode,
            'is_active' => true,
            'status' => 'active',
            'capacity' => 20,
            'booking_buffer_minutes' => 0,
        ]);
    }

    private function makeApprovedBooking(
        User $owner,
        ?Room $room = null,
        string $startsAt = '2026-05-12 10:00:00',
        string $endsAt = '2026-05-12 11:00:00',
    ): Booking {
        $room ??= $this->makeRoom();

        $booking = Booking::create([
            'booking_code' => 'BKG-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Awal',
            'agenda' => 'Agenda awal.',
            'attendee_count' => 4,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => BookingStatus::Approved->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
            'submitted_at' => Carbon::now()->subHour(),
            'approved_at' => Carbon::now(),
        ]);

        $approver = User::factory()->create(['is_active' => true]);
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

    private function makeDraftBooking(User $owner, ?Room $room = null): Booking
    {
        $room ??= $this->makeRoom();

        return Booking::create([
            'booking_code' => 'BKG-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Draf Rapat',
            'agenda' => null,
            'attendee_count' => 4,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 11:00:00',
            'status' => BookingStatus::Draft->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
        ]);
    }

    // ─── RENDER & PREFILL ────────────────────────────────────────────

    public function test_owner_can_render_the_reschedule_form(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeApprovedBooking($owner);

        $this->actingAs($owner)
            ->get(route('bookings.reschedule', $booking))
            ->assertOk();
    }

    public function test_reschedule_form_is_prefilled_from_the_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeApprovedBooking($owner);

        $this->actingAs($owner);

        Livewire::test(BookingForm::class, ['booking' => $booking, 'reschedule' => true])
            ->assertSet('mode', 'reschedule')
            ->assertSet('bookingId', $booking->id)
            ->assertSet('roomId', (string) $booking->resource_id)
            ->assertSet('subject', 'Rapat Awal')
            ->assertSet('attendeeCount', 4)
            ->assertSet('startsAt', '2026-05-12T10:00');
    }

    // ─── SUBMIT ──────────────────────────────────────────────────────

    public function test_submitting_the_reschedule_creates_a_new_booking_and_cancels_the_original(): void
    {
        $owner = $this->makeRequester();
        $room = $this->makeRoom('none');
        $booking = $this->makeApprovedBooking($owner, $room);

        $this->actingAs($owner);

        $component = Livewire::test(BookingForm::class, ['booking' => $booking, 'reschedule' => true])
            ->set('subject', 'Rapat Dijadwalkan Ulang')
            ->call('submit');

        /** @var Booking $newBooking */
        $newBooking = Booking::query()
            ->where('rescheduled_from_booking_id', $booking->id)
            ->firstOrFail();

        $component->assertRedirect(route('bookings.show', $newBooking));

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Cancelled->value,
        ]);
        $this->assertSame('Rapat Dijadwalkan Ulang', $newBooking->subject);
        $this->assertNotSame($booking->id, $newBooking->id);
    }

    public function test_reschedule_submit_with_a_conflict_shows_the_banner(): void
    {
        $owner = $this->makeRequester();
        $room = $this->makeRoom('none');
        $booking = $this->makeApprovedBooking($owner, $room);

        // Another approved booking already occupies 14:00-15:00 in the room.
        $this->makeApprovedBooking(
            $this->makeRequester(),
            $room,
            '2026-05-12 14:00:00',
            '2026-05-12 15:00:00',
        );

        $this->actingAs($owner);

        Livewire::test(BookingForm::class, ['booking' => $booking, 'reschedule' => true])
            ->set('startsAt', '2026-05-12T14:00')
            ->set('endsAt', '2026-05-12T15:00')
            ->call('submit')
            ->assertNoRedirect()
            ->assertSee('bentrok');

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Approved->value,
        ]);
    }

    // ─── ROUTE AUTHORIZATION ─────────────────────────────────────────

    public function test_non_owner_is_forbidden_from_the_reschedule_route(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeApprovedBooking($owner);
        $stranger = $this->makeRequester();

        $this->actingAs($stranger)
            ->get(route('bookings.reschedule', $booking))
            ->assertForbidden();
    }

    public function test_guest_is_redirected_from_the_reschedule_route(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeApprovedBooking($owner);

        $this->get(route('bookings.reschedule', $booking))
            ->assertRedirect(route('login'));
    }

    public function test_reschedule_route_is_forbidden_for_a_non_approved_booking(): void
    {
        $owner = $this->makeRequester();
        $draft = $this->makeDraftBooking($owner);

        $this->actingAs($owner)
            ->get(route('bookings.reschedule', $draft))
            ->assertForbidden();
    }

    // ─── SHOW-PAGE ENTRY POINT ───────────────────────────────────────

    public function test_show_page_offers_a_reschedule_link_for_an_approved_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeApprovedBooking($owner);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertSee(route('bookings.reschedule', $booking));
    }

    public function test_show_page_hides_the_reschedule_link_for_a_draft_booking(): void
    {
        $owner = $this->makeRequester();
        $booking = $this->makeDraftBooking($owner);

        $this->actingAs($owner)
            ->get(route('bookings.show', $booking))
            ->assertDontSee(route('bookings.reschedule', $booking));
    }
}
