<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\SubmitBookingAction;
use App\Enums\BookingStatus;
use App\Livewire\Approval\ApprovalInbox;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\BookingSubmittedNotification;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * End-to-end approval-journey tests for Sprint 2D-G.
 *
 * Unlike the per-layer tests (action units, ApprovalInbox component,
 * notification cascade), these thread a booking through the whole sequence
 * in one flow: a real submit produces a booking that the assigned approver
 * actually finds in their live inbox, and acting on it there leaves the
 * booking, its approval row, status history, audit log and notifications
 * all consistent.
 *
 * @see SubmitBookingAction
 * @see ApprovalInbox
 */
class ApprovalJourneyTest extends TestCase
{
    use RefreshDatabase;

    private SubmitBookingAction $submitAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
        $this->submitAction = $this->app->make(SubmitBookingAction::class);
        Carbon::setTestNow('2026-05-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    /**
     * Run a real submit into a unit_approver room — the booking lands
     * Submitted, routed to $approver's queue.
     *
     * @return array{booking: Booking, approver: User, requester: User}
     */
    private function submitToApproverQueue(): array
    {
        $approver = $this->userWithRole('unit_approver');
        $requester = $this->userWithRole('requester');
        $requester->update(['approver_user_id' => $approver->id]);

        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->submitAction->execute($requester, [
            'room_id' => $room->id,
            'subject' => 'Rapat Lintas Unit',
            'attendee_count' => 6,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ]);

        return ['booking' => $booking, 'approver' => $approver, 'requester' => $requester];
    }

    public function test_submit_to_approve_journey(): void
    {
        Notification::fake();

        $ctx = $this->submitToApproverQueue();
        $booking = $ctx['booking'];
        $approver = $ctx['approver'];
        $requester = $ctx['requester'];

        // Post-submit: Submitted, hybrid pointer set, pending approval row,
        // approver notified.
        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame(1, $booking->current_approval_step);
        $this->assertSame($approver->id, $booking->current_approver_user_id);
        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
        ]);
        Notification::assertSentTo($approver, BookingSubmittedNotification::class);

        // The approver finds it in their live inbox and approves it there.
        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->assertSee($booking->booking_code)
            ->call('approve', $booking->id)
            ->assertOk();

        // Post-approve: Approved, pointer cleared, approval row approved,
        // history + audit written, requester notified.
        $booking->refresh();
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertNull($booking->current_approval_step);
        $this->assertNull($booking->current_approver_user_id);
        $this->assertNotNull($booking->approved_at);

        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'status' => 'approved',
            'acted_by_user_id' => $approver->id,
        ]);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => 'submitted',
            'to_status' => 'approved',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'event' => 'approve',
        ]);
        Notification::assertSentTo($requester, BookingApprovedNotification::class);
    }

    public function test_submit_to_reject_journey(): void
    {
        Notification::fake();

        $ctx = $this->submitToApproverQueue();
        $booking = $ctx['booking'];
        $approver = $ctx['approver'];
        $requester = $ctx['requester'];
        $reason = 'Ruangan dialihkan untuk rapat direksi.';

        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->assertSee($booking->booking_code)
            ->call('startReject', $booking->id)
            ->set('rejectReason', $reason)
            ->call('reject')
            ->assertOk()
            ->assertHasNoErrors();

        $booking->refresh();
        $this->assertSame(BookingStatus::Rejected, $booking->status);
        $this->assertNull($booking->current_approval_step);
        $this->assertNull($booking->current_approver_user_id);
        $this->assertSame($reason, $booking->rejection_reason);

        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'status' => 'rejected',
            'action_notes' => $reason,
        ]);
        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => 'submitted',
            'to_status' => 'rejected',
        ]);
        Notification::assertSentTo($requester, BookingRejectedNotification::class);
    }

    public function test_auto_approved_booking_bypasses_the_approval_queue(): void
    {
        Notification::fake();

        $requester = $this->userWithRole('requester');
        $approver = $this->userWithRole('unit_approver');
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->submitAction->execute($requester, [
            'room_id' => $room->id,
            'subject' => 'Rapat Mandiri',
            'attendee_count' => 3,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ]);

        // No approval subsystem involvement at all.
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertNull($booking->current_approver_user_id);
        $this->assertDatabaseMissing('booking_approvals', ['booking_id' => $booking->id]);
        Notification::assertNothingSent();

        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->assertDontSee($booking->booking_code);
    }

    public function test_approve_is_blocked_when_the_slot_is_taken_after_submit(): void
    {
        $ctx = $this->submitToApproverQueue();
        $booking = $ctx['booking'];
        $approver = $ctx['approver'];

        // A competing booking grabs an overlapping slot after submit.
        $poacher = $this->userWithRole('requester');
        Booking::create([
            'booking_code' => 'BKG-20260505-POACH',
            'room_id' => $booking->room_id,
            'requester_user_id' => $poacher->id,
            'created_by_user_id' => $poacher->id,
            'subject' => 'Bentrok',
            'attendee_count' => 2,
            'starts_at' => '2026-05-05 10:30:00',
            'ends_at' => '2026-05-05 11:30:00',
            'status' => BookingStatus::Approved->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'none',
            'approved_at' => Carbon::now(),
        ]);

        // Approving via the inbox triggers the re-check; the component catches
        // the conflict and the booking is left untouched.
        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->call('approve', $booking->id)
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame($approver->id, $booking->current_approver_user_id);
        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'status' => 'pending',
        ]);
    }
}
