<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire\Approval;

use App\Enums\BookingStatus;
use App\Livewire\Approval\ApprovalInbox;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Feature tests for the ApprovalInbox Livewire component (GET /approvals).
 *
 * Covers: the queue shows only the signed-in approver's pending bookings;
 * inline approve / reject delegate to the actions and transition status;
 * reject requires a reason; access is gated to bookings.approve holders.
 *
 * @see ApprovalInbox
 */
class ApprovalInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
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
     * Build a Submitted booking with a pending BookingApproval row at
     * sequence 1, assigned to $approver — the post-submit state that
     * ApproveBookingAction / RejectBookingAction expect.
     */
    private function createSubmittedBooking(User $approver): Booking
    {
        $requester = $this->userWithRole('requester');

        $booking = Booking::factory()->create([
            'requester_user_id' => $requester->id,
            'status' => BookingStatus::Submitted,
            'current_approval_step' => 1,
            'current_approver_user_id' => $approver->id,
        ]);

        BookingApproval::create([
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
        ]);

        return $booking;
    }

    // ─── THE QUEUE ──────────────────────────────────────────────────

    public function test_approver_sees_their_pending_bookings_in_the_queue(): void
    {
        $approver = $this->userWithRole('unit_approver');
        $booking = $this->createSubmittedBooking($approver);

        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->assertOk()
            ->assertSee($booking->booking_code);
    }

    public function test_queue_excludes_bookings_assigned_to_other_approvers(): void
    {
        $approver = $this->userWithRole('unit_approver');
        $otherApprover = $this->userWithRole('unit_approver');
        $mine = $this->createSubmittedBooking($approver);
        $theirs = $this->createSubmittedBooking($otherApprover);

        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->assertSee($mine->booking_code)
            ->assertDontSee($theirs->booking_code);
    }

    public function test_queue_excludes_non_submitted_bookings(): void
    {
        $approver = $this->userWithRole('unit_approver');
        $approved = Booking::factory()->create([
            'status' => BookingStatus::Approved,
            'current_approver_user_id' => $approver->id,
        ]);

        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->assertDontSee($approved->booking_code);
    }

    // ─── APPROVE / REJECT ───────────────────────────────────────────

    public function test_approve_transitions_the_booking_to_approved(): void
    {
        $approver = $this->userWithRole('unit_approver');
        $booking = $this->createSubmittedBooking($approver);

        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->call('approve', $booking->id)
            ->assertOk();

        $booking->refresh();
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertNull($booking->current_approver_user_id);
    }

    public function test_reject_with_a_reason_transitions_the_booking_to_rejected(): void
    {
        $approver = $this->userWithRole('unit_approver');
        $booking = $this->createSubmittedBooking($approver);

        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->call('startReject', $booking->id)
            ->set('rejectReason', 'Ruangan dialihkan untuk acara mendadak.')
            ->call('reject')
            ->assertOk()
            ->assertHasNoErrors();

        $booking->refresh();
        $this->assertSame(BookingStatus::Rejected, $booking->status);
    }

    public function test_reject_requires_a_reason(): void
    {
        $approver = $this->userWithRole('unit_approver');
        $booking = $this->createSubmittedBooking($approver);

        Livewire::actingAs($approver)
            ->test(ApprovalInbox::class)
            ->call('startReject', $booking->id)
            ->set('rejectReason', '')
            ->call('reject')
            ->assertHasErrors('rejectReason');

        $booking->refresh();
        $this->assertSame(BookingStatus::Submitted, $booking->status);
    }

    // ─── ACCESS CONTROL ─────────────────────────────────────────────

    public function test_non_approver_cannot_access_the_inbox(): void
    {
        $requester = $this->userWithRole('requester');

        $this->actingAs($requester)
            ->get('/approvals')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/approvals')->assertRedirect('/login');
    }
}
