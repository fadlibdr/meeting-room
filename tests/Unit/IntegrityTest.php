<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Actions\ApproveBookingAction;
use App\Actions\RejectBookingAction;
use App\Actions\SubmitBookingAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Role;
use App\Models\Room;
use App\Models\User;
use Database\Seeders\AppSettingsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Integrity tests for the Dec-03 hybrid approver pointer.
 *
 * The invariant (Database Schema v2, section G.4):
 *  - A Submitted booking MUST carry a non-null current_approval_step and
 *    current_approver_user_id, and a booking_approvals row must exist at that
 *    step whose approver_user_id matches the pointer and whose status is
 *    'pending'.
 *  - A booking in any other status MUST have both pointer columns null.
 *
 * assertHybridPointerConsistent() iterates every booking in the database and
 * is written to be reusable as a nightly production integrity check, not just
 * a fixture-bound assertion (Blueprint v3 section M.4).
 *
 * @see SubmitBookingAction
 * @see ApproveBookingAction
 * @see RejectBookingAction
 */
class IntegrityTest extends TestCase
{
    use RefreshDatabase;

    private SubmitBookingAction $submitAction;

    private ApproveBookingAction $approveAction;

    private RejectBookingAction $rejectAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(AppSettingsSeeder::class);
        $this->submitAction = $this->app->make(SubmitBookingAction::class);
        $this->approveAction = $this->app->make(ApproveBookingAction::class);
        $this->rejectAction = $this->app->make(RejectBookingAction::class);
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
     * Submit one booking through the real pipeline into a unit_approver room.
     * Each call uses a fresh room, so callers may submit many without conflict.
     *
     * @return array{booking: Booking, approver: User}
     */
    private function submitBooking(): array
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
            'subject' => 'Rapat Integritas',
            'attendee_count' => 4,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ]);

        return ['booking' => $booking, 'approver' => $approver];
    }

    /**
     * Assert the Dec-03 hybrid-pointer invariant for every booking in the DB.
     */
    private function assertHybridPointerConsistent(): void
    {
        foreach (Booking::query()->get() as $booking) {
            if ($booking->status === BookingStatus::Submitted) {
                $this->assertNotNull(
                    $booking->current_approval_step,
                    "Submitted booking #{$booking->id} has a null current_approval_step.",
                );
                $this->assertNotNull(
                    $booking->current_approver_user_id,
                    "Submitted booking #{$booking->id} has a null current_approver_user_id.",
                );

                $step = BookingApproval::query()
                    ->where('booking_id', $booking->id)
                    ->where('sequence_no', $booking->current_approval_step)
                    ->first();

                if ($step === null) {
                    $this->fail(
                        "Submitted booking #{$booking->id} has no approval row at step "
                        .$booking->current_approval_step.'.'
                    );
                }

                $this->assertSame(
                    $booking->current_approver_user_id,
                    $step->approver_user_id,
                    "Submitted booking #{$booking->id}: pointer disagrees with its approval row.",
                );
                $this->assertSame(
                    'pending',
                    $step->status,
                    "Submitted booking #{$booking->id}: current approval row is not pending.",
                );

                continue;
            }

            $this->assertNull(
                $booking->current_approval_step,
                "Non-submitted booking #{$booking->id} ({$booking->status->value}) still carries a current_approval_step.",
            );
            $this->assertNull(
                $booking->current_approver_user_id,
                "Non-submitted booking #{$booking->id} ({$booking->status->value}) still carries a current_approver_user_id.",
            );
        }
    }

    public function test_submitted_bookings_carry_a_consistent_pointer(): void
    {
        $this->submitBooking();
        $this->submitBooking();
        $this->submitBooking();

        $this->assertSame(
            3,
            Booking::query()->where('status', BookingStatus::Submitted->value)->count(),
        );
        $this->assertHybridPointerConsistent();
    }

    public function test_invariant_holds_across_submitted_terminal_and_draft_bookings(): void
    {
        // Two remain Submitted.
        $this->submitBooking();
        $this->submitBooking();

        // One submitted then approved — pointer must be cleared.
        $approved = $this->submitBooking();
        $this->approveAction->execute($approved['booking'], $approved['approver']);

        // One submitted then rejected — pointer must be cleared.
        $rejected = $this->submitBooking();
        $this->rejectAction->execute(
            $rejected['booking'],
            $rejected['approver'],
            'Tidak memenuhi persyaratan ruangan.',
        );

        // One draft, never submitted — pointer null by construction.
        Booking::factory()->create(['status' => BookingStatus::Draft]);

        $this->assertHybridPointerConsistent();
    }
}
