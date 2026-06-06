<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\ApproveBookingAction;
use App\Actions\RejectBookingAction;
use App\Actions\SubmitBookingAction;
use App\Enums\ApprovalStepType;
use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalPolicy;
use App\Models\ApprovalPolicyStep;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingSubmittedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MultiStepApprovalTest extends TestCase
{
    use RefreshDatabase;

    private User $approver1;

    private User $approver2;

    private User $requester;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Carbon::setTestNow('2026-05-05 09:00:00');

        $this->approver1 = User::factory()->create(['is_active' => true]);
        $this->approver2 = User::factory()->create(['is_active' => true]);
        $this->approver2->roles()->attach(Role::where('code', 'ga_admin')->firstOrFail()->id, ['assigned_at' => now()]);

        $unit = Unit::factory()->create();
        $this->requester = User::factory()->create([
            'unit_id' => $unit->id,
            'approver_user_id' => $this->approver1->id,
        ]);

        // 2-step chain: requester's unit approver, then a GA Admin.
        $policy = ApprovalPolicy::factory()->create();
        ApprovalPolicyStep::factory()->create([
            'approval_policy_id' => $policy->id, 'sequence_no' => 1,
            'approver_type' => ApprovalStepType::UnitApprover,
        ]);
        ApprovalPolicyStep::factory()->create([
            'approval_policy_id' => $policy->id, 'sequence_no' => 2,
            'approver_type' => ApprovalStepType::Role,
            'role_id' => Role::where('code', 'ga_admin')->firstOrFail()->id,
        ]);

        // Policy overrides the per-room mode.
        $this->room = Room::factory()->create([
            'is_active' => true,
            'status' => 'active',
            'approval_mode' => RoomApprovalMode::None->value,
            'approval_policy_id' => $policy->id,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function submit(): Booking
    {
        return app(SubmitBookingAction::class)->execute($this->requester, [
            'room_id' => $this->room->id,
            'subject' => 'Rapat Multi-Step',
            'attendee_count' => 5,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ], notify: false);
    }

    private function assertPointerIntegrity(Booking $booking): void
    {
        $booking->refresh();
        if ($booking->status === BookingStatus::Submitted) {
            $this->assertNotNull($booking->current_approval_step);
            $row = $booking->approvals()->where('sequence_no', $booking->current_approval_step)->first();
            $this->assertNotNull($row, 'No approval row at the current step.');
            $this->assertSame($booking->current_approver_user_id, $row->approver_user_id);
            $this->assertSame('pending', $row->status);
        } else {
            $this->assertNull($booking->current_approval_step);
            $this->assertNull($booking->current_approver_user_id);
        }
    }

    public function test_submit_creates_the_full_chain_and_points_to_step_one(): void
    {
        $booking = $this->submit();

        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame(1, $booking->current_approval_step);
        $this->assertSame($this->approver1->id, $booking->current_approver_user_id);
        $this->assertSame(2, $booking->approvals()->count());
        $this->assertSame(2, $booking->approvals()->where('status', 'pending')->count());
        $this->assertPointerIntegrity($booking);
    }

    public function test_chain_advances_then_finalizes_notifying_the_right_party_each_time(): void
    {
        Notification::fake();
        $booking = $this->submit();

        // Step 1 approved -> advance to step 2, still Submitted, notify approver2.
        app(ApproveBookingAction::class)->execute($booking, $this->approver1);
        $booking->refresh();
        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame(2, $booking->current_approval_step);
        $this->assertSame($this->approver2->id, $booking->current_approver_user_id);
        $this->assertPointerIntegrity($booking);
        Notification::assertSentTo($this->approver2, BookingSubmittedNotification::class);

        // Step 2 (last) approved -> finalize to Approved, notify the requester.
        app(ApproveBookingAction::class)->execute($booking, $this->approver2);
        $booking->refresh();
        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertNotNull($booking->approved_at);
        $this->assertSame(2, $booking->approvals()->where('status', 'approved')->count());
        $this->assertPointerIntegrity($booking);
        Notification::assertSentTo($this->requester, BookingApprovedNotification::class);
    }

    public function test_reject_mid_chain_terminates_the_booking(): void
    {
        $booking = $this->submit();

        // Approve step 1, then reject at step 2.
        app(ApproveBookingAction::class)->execute($booking, $this->approver1);
        app(RejectBookingAction::class)->execute($booking->refresh(), $this->approver2, 'Tidak disetujui GA.');

        $booking->refresh();
        $this->assertSame(BookingStatus::Rejected, $booking->status);
        $this->assertNull($booking->current_approval_step);
        $this->assertNull($booking->current_approver_user_id);
    }

    public function test_delegation_reroutes_the_pointer_at_submit(): void
    {
        $delegate = User::factory()->create(['is_active' => true]);
        ApprovalDelegation::factory()->create([
            'from_user_id' => $this->approver1->id,
            'to_user_id' => $delegate->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $booking = $this->submit();

        $this->assertSame($delegate->id, $booking->current_approver_user_id);
        $this->assertSame($delegate->id, $booking->approvals()->where('sequence_no', 1)->value('approver_user_id'));
    }
}
