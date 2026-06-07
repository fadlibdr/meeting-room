<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Enums\BookingStatus;
use App\Http\Controllers\BookingController;
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
use Tests\TestCase;

/**
 * Feature tests for the submit-Draft HTTP flow (M3-C2-iii): the
 * POST bookings/{booking}/submit route, BookingController::submit, and
 * the show-page "Ajukan" button. SubmitDraftAction's mechanics are owned
 * by SubmitDraftActionTest; these cover the HTTP wiring.
 *
 * @see BookingController::submit
 */
class SubmitDraftBookingTest extends TestCase
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

    private function makeRequester(?User $approver = null): User
    {
        $unit = Unit::factory()->create();
        $user = User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
            'approver_user_id' => $approver?->id,
        ]);

        return $this->attachRole($user, 'requester');
    }

    private function makeRoom(string $approvalMode): Room
    {
        return Room::factory()->create([
            'approval_mode' => $approvalMode,
            'is_active' => true,
            'status' => 'active',
            'capacity' => 20,
            'booking_buffer_minutes' => 0,
        ]);
    }

    private function makeBooking(
        BookingStatus $status,
        User $owner,
        Room $room,
        string $startsAt = '2026-05-06 10:00:00',
        string $endsAt = '2026-05-06 11:00:00',
    ): Booking {
        $attributes = [
            'booking_code' => 'BKG-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Koordinasi',
            'agenda' => 'Agenda.',
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
            $attributes['approved_at'] = Carbon::now();
        }

        return Booking::create($attributes);
    }

    // ─── submit ──────────────────────────────────────────────────────

    public function test_owner_can_submit_their_draft(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeBooking(BookingStatus::Draft, $owner, $this->makeRoom('unit_approver'));

        $this->actingAs($owner)
            ->post(route('bookings.submit', $draft->id))
            ->assertRedirect(route('bookings.show', $draft->id));

        $this->assertDatabaseHas('bookings', [
            'id' => $draft->id,
            'status' => BookingStatus::Submitted->value,
        ]);
    }

    public function test_submitting_a_draft_to_an_auto_approve_room_approves_it(): void
    {
        $owner = $this->makeRequester();
        $draft = $this->makeBooking(BookingStatus::Draft, $owner, $this->makeRoom('none'));

        $this->actingAs($owner)
            ->post(route('bookings.submit', $draft->id))
            ->assertRedirect(route('bookings.show', $draft->id));

        $this->assertDatabaseHas('bookings', [
            'id' => $draft->id,
            'status' => BookingStatus::Approved->value,
        ]);
    }

    public function test_non_owner_cannot_submit_the_draft(): void
    {
        $owner = $this->makeRequester();
        $draft = $this->makeBooking(BookingStatus::Draft, $owner, $this->makeRoom('none'));
        $stranger = $this->makeRequester();

        $this->actingAs($stranger)
            ->post(route('bookings.submit', $draft->id))
            ->assertForbidden();

        $this->assertDatabaseHas('bookings', [
            'id' => $draft->id,
            'status' => BookingStatus::Draft->value,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $owner = $this->makeRequester();
        $draft = $this->makeBooking(BookingStatus::Draft, $owner, $this->makeRoom('none'));

        $this->post(route('bookings.submit', $draft->id))
            ->assertRedirect(route('login'));
    }

    public function test_cannot_submit_a_non_draft_booking(): void
    {
        $owner = $this->makeRequester();
        $submitted = $this->makeBooking(BookingStatus::Submitted, $owner, $this->makeRoom('unit_approver'));

        $this->actingAs($owner)
            ->post(route('bookings.submit', $submitted->id))
            ->assertForbidden();
    }

    public function test_submitting_a_draft_with_a_taken_slot_shows_an_error(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $room = $this->makeRoom('unit_approver');
        $draft = $this->makeBooking(BookingStatus::Draft, $owner, $room);
        $this->makeBooking(
            BookingStatus::Approved,
            $this->makeRequester(),
            $room,
            '2026-05-06 10:00:00',
            '2026-05-06 11:00:00',
        );

        $this->actingAs($owner)
            ->from(route('bookings.show', $draft->id))
            ->post(route('bookings.submit', $draft->id))
            ->assertRedirect(route('bookings.show', $draft->id))
            ->assertSessionHasErrors('submit');

        $this->assertDatabaseHas('bookings', [
            'id' => $draft->id,
            'status' => BookingStatus::Draft->value,
        ]);
    }

    // ─── show-page button ────────────────────────────────────────────

    public function test_show_page_offers_a_submit_button_for_a_draft(): void
    {
        $owner = $this->makeRequester();
        $draft = $this->makeBooking(BookingStatus::Draft, $owner, $this->makeRoom('none'));

        $this->actingAs($owner)
            ->get(route('bookings.show', $draft->id))
            ->assertSee(route('bookings.submit', $draft->id));
    }

    public function test_show_page_hides_the_submit_button_for_a_submitted_booking(): void
    {
        $owner = $this->makeRequester();
        $submitted = $this->makeBooking(BookingStatus::Submitted, $owner, $this->makeRoom('unit_approver'));

        $this->actingAs($owner)
            ->get(route('bookings.show', $submitted->id))
            ->assertDontSee(route('bookings.submit', $submitted->id));
    }
}
