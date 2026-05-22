<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Policies\BookingPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingPolicyTest extends TestCase
{
    use RefreshDatabase;

    private BookingPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->policy = new BookingPolicy;
    }

    private function userWithRole(string $roleCode): User
    {
        $user = User::factory()->create();
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->sync([$role->id]);

        return $user;
    }

    private function makeBooking(User $owner, BookingStatus $status, ?User $approver = null): Booking
    {
        $booking = Booking::factory()->state([
            'requester_user_id' => $owner->id,
            'status' => $status,
        ]);

        if ($status === BookingStatus::Submitted && $approver !== null) {
            $booking = $booking->state([
                'current_approver_user_id' => $approver->id,
                'current_approval_step' => 1,
            ]);
        }

        return $booking->create();
    }

    // ─── viewAny ────────────────────────────────────────────────────

    /**
     * @dataProvider viewAnyMatrix
     */
    public function test_view_any_authorization(string $roleCode, bool $expected): void
    {
        $user = $this->userWithRole($roleCode);
        $this->assertSame($expected, $this->policy->viewAny($user));
    }

    public static function viewAnyMatrix(): array
    {
        return [
            'super_admin can view list' => ['super_admin', true],
            'system_admin cannot view list (no booking perms)' => ['system_admin', false],
            'ga_admin can view list' => ['ga_admin', true],
            'unit_approver can view list' => ['unit_approver', true],
            'requester can view list' => ['requester', true],
        ];
    }

    // ─── view (specific booking) ────────────────────────────────────

    public function test_owner_can_view_own_booking(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Draft);

        $this->assertTrue($this->policy->view($user, $booking));
    }

    public function test_requester_cannot_view_others_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $other = $this->userWithRole('requester');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertFalse($this->policy->view($other, $booking));
    }

    public function test_ga_admin_can_view_others_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertTrue($this->policy->view($admin, $booking));
    }

    public function test_super_admin_can_view_any_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertTrue($this->policy->view($admin, $booking));
    }

    // ─── create ─────────────────────────────────────────────────────

    /**
     * @dataProvider createMatrix
     */
    public function test_create_authorization(string $roleCode, bool $expected): void
    {
        $user = $this->userWithRole($roleCode);
        $this->assertSame($expected, $this->policy->create($user));
    }

    public static function createMatrix(): array
    {
        return [
            'super_admin can create' => ['super_admin', true],
            'system_admin cannot create' => ['system_admin', false],
            'ga_admin can create' => ['ga_admin', false],  // no bookings.create
            'unit_approver can create' => ['unit_approver', true],
            'requester can create' => ['requester', true],
        ];
    }

    // ─── update ─────────────────────────────────────────────────────

    public function test_owner_can_update_own_draft(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Draft);

        $this->assertTrue($this->policy->update($user, $booking));
    }

    public function test_owner_can_update_own_submitted(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Submitted);

        $this->assertTrue($this->policy->update($user, $booking));
    }

    public function test_owner_cannot_update_own_approved(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Approved);

        $this->assertFalse($this->policy->update($user, $booking));
    }

    public function test_owner_cannot_update_own_completed(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Completed);

        $this->assertFalse($this->policy->update($user, $booking));
    }

    public function test_super_admin_can_update_others_draft(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertTrue($this->policy->update($admin, $booking));
    }

    public function test_requester_cannot_update_others_draft(): void
    {
        $owner = $this->userWithRole('requester');
        $other = $this->userWithRole('requester');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertFalse($this->policy->update($other, $booking));
    }

    // ─── delete ─────────────────────────────────────────────────────

    public function test_super_admin_can_delete_any_draft_via_permission(): void
    {
        // Per Sprint 1 matrix: only super_admin has bookings.delete
        $admin = $this->userWithRole('super_admin');
        $owner = $this->userWithRole('requester');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertTrue($this->policy->delete($admin, $booking));
    }

    public function test_requester_cannot_delete_own_draft(): void
    {
        // Requester has NO bookings.delete permission per seeder
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Draft);

        $this->assertFalse($this->policy->delete($user, $booking));
    }

    public function test_owner_cannot_delete_own_submitted(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Submitted);

        $this->assertFalse($this->policy->delete($user, $booking));
    }

    public function test_ga_admin_cannot_delete_any_draft(): void
    {
        // GA admin has no bookings.delete per seeder
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertFalse($this->policy->delete($admin, $booking));
    }

    // ─── submit ─────────────────────────────────────────────────────

    public function test_owner_can_submit_own_draft(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Draft);

        $this->assertTrue($this->policy->submit($user, $booking));
    }

    public function test_owner_cannot_submit_own_submitted(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Submitted);

        $this->assertFalse($this->policy->submit($user, $booking));
    }

    public function test_admin_cannot_submit_others_draft(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertFalse($this->policy->submit($admin, $booking));
    }

    // ─── cancel ─────────────────────────────────────────────────────

    public function test_owner_can_cancel_own_draft(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Draft);

        $this->assertTrue($this->policy->cancel($user, $booking));
    }

    public function test_owner_can_cancel_own_submitted(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Submitted);

        $this->assertTrue($this->policy->cancel($user, $booking));
    }

    public function test_owner_can_cancel_own_approved(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Approved);

        $this->assertTrue($this->policy->cancel($user, $booking));
    }

    public function test_owner_cannot_cancel_own_rejected(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Rejected);

        $this->assertFalse($this->policy->cancel($user, $booking));
    }

    public function test_owner_cannot_cancel_own_completed(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Completed);

        $this->assertFalse($this->policy->cancel($user, $booking));
    }

    public function test_ga_admin_cannot_cancel_others_approved(): void
    {
        // GA admin has no bookings.cancel per seeder; cancel restricted to owners + super_admin
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Approved);

        $this->assertFalse($this->policy->cancel($admin, $booking));
    }

    public function test_super_admin_can_cancel_others_approved(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Approved);

        $this->assertTrue($this->policy->cancel($admin, $booking));
    }

    public function test_requester_cannot_cancel_others_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $other = $this->userWithRole('requester');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted);

        $this->assertFalse($this->policy->cancel($other, $booking));
    }

    // ─── approve ────────────────────────────────────────────────────

    public function test_assigned_approver_can_approve(): void
    {
        $owner = $this->userWithRole('requester');
        $approver = $this->userWithRole('unit_approver');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $approver);

        $this->assertTrue($this->policy->approve($approver, $booking));
    }

    public function test_unassigned_unit_approver_cannot_approve(): void
    {
        $owner = $this->userWithRole('requester');
        $assigned = $this->userWithRole('unit_approver');
        $other = $this->userWithRole('unit_approver');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $assigned);

        // unit_approver does not have view-all, so should fail
        $this->assertFalse($this->policy->approve($other, $booking));
    }

    public function test_ga_admin_cannot_approve_booking_assigned_to_unit_approver(): void
    {
        // Per corrected policy: only the assigned approver (current_approver_user_id) approves
        // view-all does NOT grant approval authority, only read scope
        $owner = $this->userWithRole('requester');
        $approver = $this->userWithRole('unit_approver');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $approver);

        $this->assertFalse($this->policy->approve($admin, $booking));
    }

    public function test_ga_admin_can_approve_when_assigned_as_current_approver(): void
    {
        // GA admin gets approve authority via current_approver_user_id assignment
        // (rooms with approval_mode=ga_admin route bookings to ga_admin)
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $admin);

        $this->assertTrue($this->policy->approve($admin, $booking));
    }

    public function test_requester_cannot_approve(): void
    {
        $owner = $this->userWithRole('requester');
        $approver = $this->userWithRole('unit_approver');
        $other = $this->userWithRole('requester');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $approver);

        $this->assertFalse($this->policy->approve($other, $booking));
    }

    public function test_cannot_approve_draft_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);

        $this->assertFalse($this->policy->approve($admin, $booking));
    }

    public function test_cannot_approve_already_approved_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Approved);

        $this->assertFalse($this->policy->approve($admin, $booking));
    }

    // ─── reject ─────────────────────────────────────────────────────

    public function test_assigned_approver_can_reject(): void
    {
        $owner = $this->userWithRole('requester');
        $approver = $this->userWithRole('unit_approver');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $approver);

        $this->assertTrue($this->policy->reject($approver, $booking));
    }

    public function test_ga_admin_cannot_reject_booking_assigned_to_unit_approver(): void
    {
        $owner = $this->userWithRole('requester');
        $approver = $this->userWithRole('unit_approver');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $approver);

        $this->assertFalse($this->policy->reject($admin, $booking));
    }

    public function test_ga_admin_can_reject_when_assigned_as_current_approver(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $admin);

        $this->assertTrue($this->policy->reject($admin, $booking));
    }

    public function test_requester_cannot_reject(): void
    {
        $owner = $this->userWithRole('requester');
        $approver = $this->userWithRole('unit_approver');
        $other = $this->userWithRole('requester');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $approver);

        $this->assertFalse($this->policy->reject($other, $booking));
    }

    public function test_cannot_reject_already_rejected_booking(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Rejected);

        $this->assertFalse($this->policy->reject($admin, $booking));
    }

    // ─── 2D-C: Super Admin override (bookings.override permission) ───

    public function test_super_admin_can_approve_a_booking_they_are_not_assigned_to(): void
    {
        $owner = $this->userWithRole('requester');
        $assignedApprover = $this->userWithRole('unit_approver');
        $superAdmin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $assignedApprover);
        $this->assertTrue($this->policy->approve($superAdmin, $booking));
    }

    public function test_super_admin_can_reject_a_booking_they_are_not_assigned_to(): void
    {
        $owner = $this->userWithRole('requester');
        $assignedApprover = $this->userWithRole('unit_approver');
        $superAdmin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $assignedApprover);
        $this->assertTrue($this->policy->reject($superAdmin, $booking));
    }

    public function test_ga_admin_cannot_approve_a_booking_not_assigned_to_them(): void
    {
        // Regression guard: bookings.override is super_admin-only. ga_admin
        // has bookings.approve but NOT bookings.override, so the assignment
        // check still applies to it.
        $owner = $this->userWithRole('requester');
        $assignedApprover = $this->userWithRole('unit_approver');
        $gaAdmin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Submitted, $assignedApprover);
        $this->assertFalse($this->policy->approve($gaAdmin, $booking));
    }

    public function test_super_admin_still_cannot_approve_a_draft_booking(): void
    {
        // The override bypasses the assignment check, NOT the status gate.
        $owner = $this->userWithRole('requester');
        $superAdmin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Draft);
        $this->assertFalse($this->policy->approve($superAdmin, $booking));
    }

    // ─── reschedule (M3-E / M3-Dec-2) ────────────────────────────────

    public function test_owner_can_reschedule_own_approved(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Approved);

        $this->assertTrue($this->policy->reschedule($user, $booking));
    }

    public function test_owner_cannot_reschedule_own_draft(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Draft);

        $this->assertFalse($this->policy->reschedule($user, $booking));
    }

    public function test_owner_cannot_reschedule_own_submitted(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Submitted);

        $this->assertFalse($this->policy->reschedule($user, $booking));
    }

    public function test_owner_cannot_reschedule_own_rejected(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Rejected);

        $this->assertFalse($this->policy->reschedule($user, $booking));
    }

    public function test_owner_cannot_reschedule_own_completed(): void
    {
        $user = $this->userWithRole('requester');
        $booking = $this->makeBooking($user, BookingStatus::Completed);

        $this->assertFalse($this->policy->reschedule($user, $booking));
    }

    public function test_ga_admin_cannot_reschedule_others_approved(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('ga_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Approved);

        $this->assertFalse($this->policy->reschedule($admin, $booking));
    }

    public function test_super_admin_can_reschedule_others_approved(): void
    {
        $owner = $this->userWithRole('requester');
        $admin = $this->userWithRole('super_admin');
        $booking = $this->makeBooking($owner, BookingStatus::Approved);

        $this->assertTrue($this->policy->reschedule($admin, $booking));
    }

    public function test_requester_cannot_reschedule_others_approved(): void
    {
        $owner = $this->userWithRole('requester');
        $other = $this->userWithRole('requester');
        $booking = $this->makeBooking($owner, BookingStatus::Approved);

        $this->assertFalse($this->policy->reschedule($other, $booking));
    }
}
