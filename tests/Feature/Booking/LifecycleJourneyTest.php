<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\ApproveBookingAction;
use App\Actions\CancelBookingAction;
use App\Actions\SubmitDraftAction;
use App\Actions\UpdateBookingAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Cross-operation lifecycle journeys (M3-G-ii).
 *
 * The per-action unit tests and per-route feature tests each verify one
 * transition in isolation; these walk a booking through a chain of M3
 * edges and assert the Dec-03 hybrid-pointer invariant after every hop.
 *
 * The chain that matters most is submit -> revert -> re-submit: M3-Dec-1's
 * revert keeps the prior booking_approvals row as cancelled, so a re-submit
 * must advance booking_approvals.sequence_no rather than collide on
 * unique(booking_id, sequence_no). Both journeys below land on a
 * re-submitted (sequence_no 2) booking and drive it to a different terminal
 * state — approved, and cancelled.
 *
 * Actions are invoked directly: they are actor-agnostic, and route/Livewire
 * authorization is already covered by the per-route feature tests.
 *
 * @see SubmitDraftAction
 * @see UpdateBookingAction
 */
class LifecycleJourneyTest extends TestCase
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

    private function makeRoom(): Room
    {
        return Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 20,
            'booking_buffer_minutes' => 0,
        ]);
    }

    private function makeUser(?User $approver = null): User
    {
        $unit = Unit::factory()->create();

        return User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
            'approver_user_id' => $approver?->id,
        ]);
    }

    private function makeDraft(User $owner, Room $room): Booking
    {
        return Booking::create([
            'booking_code' => 'BKG-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6)),
            'resource_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Koordinasi',
            'agenda' => 'Agenda awal.',
            'attendee_count' => 4,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 11:00:00',
            'status' => BookingStatus::Draft->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
        ]);
    }

    /**
     * @return array{resource_id: int, subject: string, agenda: string, attendee_count: int, starts_at: string, ends_at: string}
     */
    private function editPayload(Room $room, string $agenda): array
    {
        return [
            'resource_id' => $room->id,
            'subject' => 'Rapat Koordinasi',
            'agenda' => $agenda,
            'attendee_count' => 4,
            'starts_at' => '2026-05-12 10:00:00',
            'ends_at' => '2026-05-12 11:00:00',
        ];
    }

    /**
     * Asserts the Dec-03 hybrid-pointer invariant: a Submitted booking
     * carries a pointer to a pending approval row whose approver matches;
     * a booking in any other status carries a null pointer.
     */
    private function assertPointerInvariant(Booking $booking): void
    {
        $fresh = $booking->fresh();
        $this->assertNotNull($fresh);

        if ($fresh->status === BookingStatus::Submitted) {
            $this->assertNotNull(
                $fresh->current_approval_step,
                'A Submitted booking must carry an approval step.',
            );
            $this->assertNotNull(
                $fresh->current_approver_user_id,
                'A Submitted booking must carry a current approver.',
            );

            $row = $fresh->approvals()
                ->where('sequence_no', $fresh->current_approval_step)
                ->first();

            $this->assertNotNull(
                $row,
                'The pointer must reference an existing approval row.',
            );
            $this->assertSame(
                $fresh->current_approver_user_id,
                $row->approver_user_id,
                'The pointer approver must match the approval row.',
            );
            $this->assertSame(
                'pending',
                $row->status,
                'The current-step approval row must be pending.',
            );

            return;
        }

        $this->assertNull(
            $fresh->current_approval_step,
            "A {$fresh->status->value} booking must not carry an approval step.",
        );
        $this->assertNull(
            $fresh->current_approver_user_id,
            "A {$fresh->status->value} booking must not carry a current approver.",
        );
    }

    public function test_a_resubmitted_booking_walks_through_to_approval(): void
    {
        $approver = $this->makeUser();
        $owner = $this->makeUser($approver);
        $room = $this->makeRoom();
        $booking = $this->makeDraft($owner, $room);

        // Hop 1 — edit the Draft in place. Not a transition: no history.
        app(UpdateBookingAction::class)->execute(
            $booking, $owner, $this->editPayload($room, 'Agenda direvisi.'),
        );
        $booking->refresh();
        $this->assertSame(BookingStatus::Draft, $booking->status);
        $this->assertSame(
            0,
            $booking->statusHistories()->count(),
            'A Draft-to-Draft edit writes no status history.',
        );
        $this->assertPointerInvariant($booking);

        // Hop 2 — submit. Draft -> Submitted; pointer + approval row at step 1.
        app(SubmitDraftAction::class)->execute($booking, $owner);
        $booking->refresh();
        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame(1, $booking->current_approval_step);
        $this->assertSame(1, $booking->approvals()->count());
        $this->assertPointerInvariant($booking);

        // Hop 3 — edit the Submitted booking. M3-Dec-1: reverts to Draft;
        // the step-1 approval row is cancelled (kept, not deleted).
        app(UpdateBookingAction::class)->execute(
            $booking, $owner, $this->editPayload($room, 'Agenda direvisi lagi.'),
        );
        $booking->refresh();
        $this->assertSame(BookingStatus::Draft, $booking->status);
        $this->assertSame(
            'cancelled',
            $booking->approvals()->where('sequence_no', 1)->value('status'),
        );
        $this->assertPointerInvariant($booking);

        // Hop 4 — re-submit. The fix: a fresh approval row at sequence_no 2,
        // not a unique-constraint collision on sequence_no 1.
        app(SubmitDraftAction::class)->execute($booking, $owner);
        $booking->refresh();
        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame(
            2,
            $booking->current_approval_step,
            'A re-submit advances to the next approval ordinal.',
        );
        $this->assertSame(
            2,
            $booking->approvals()->count(),
            'The cancelled step-1 row is kept; the re-submit adds step 2.',
        );
        $this->assertPointerInvariant($booking);

        // Hop 5 — approve. Submitted -> Approved; pointer cleared.
        app(ApproveBookingAction::class)->execute($booking, $approver, 'Disetujui.');
        $booking->refresh();
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertSame(
            'approved',
            $booking->approvals()->where('sequence_no', 2)->value('status'),
        );
        $this->assertSame(
            'cancelled',
            $booking->approvals()->where('sequence_no', 1)->value('status'),
            'Approving step 2 leaves the historical cancelled step 1 intact.',
        );
        $this->assertPointerInvariant($booking);
    }

    public function test_a_resubmitted_booking_can_be_cancelled(): void
    {
        $approver = $this->makeUser();
        $owner = $this->makeUser($approver);
        $room = $this->makeRoom();
        $booking = $this->makeDraft($owner, $room);

        // Submit, revert, re-submit — landing on a step-2 booking.
        app(SubmitDraftAction::class)->execute($booking, $owner);
        app(UpdateBookingAction::class)->execute(
            $booking->refresh(), $owner, $this->editPayload($room, 'Agenda diperbarui.'),
        );
        app(SubmitDraftAction::class)->execute($booking->refresh(), $owner);
        $booking->refresh();
        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame(2, $booking->current_approval_step);
        $this->assertPointerInvariant($booking);

        // Cancel the re-submitted booking. CancelBookingAction must resolve
        // the pending row via the step-2 pointer and clear it cleanly.
        app(CancelBookingAction::class)->execute($booking, $owner);
        $booking->refresh();
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertSame(
            'cancelled',
            $booking->approvals()->where('sequence_no', 2)->value('status'),
            'Cancelling resolves and cancels the step-2 approval row.',
        );
        $this->assertPointerInvariant($booking);
    }
}
