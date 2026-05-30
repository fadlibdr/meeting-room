<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\ApproveBookingAction;
use App\Actions\RejectBookingAction;
use App\Actions\SubmitBookingAction;
use App\Enums\BookingStatus;
use App\Enums\NotificationType;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\BookingSubmittedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Tests for the Sprint 2D-F notification cascade.
 *
 * Verifies each booking action dispatches the correct database-channel
 * notification to the correct recipient after its transaction commits:
 *  - SubmitBookingAction  -> BookingSubmittedNotification to the approver,
 *                            and only when the booking resolves to Submitted
 *  - ApproveBookingAction -> BookingApprovedNotification to the requester
 *  - RejectBookingAction  -> BookingRejectedNotification to the requester
 *
 * Also asserts the payload shape of each notification class.
 *
 * @see BookingSubmittedNotification
 * @see BookingApprovedNotification
 * @see BookingRejectedNotification
 */
class BookingNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private SubmitBookingAction $submitAction;

    private ApproveBookingAction $approveAction;

    private RejectBookingAction $rejectAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
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

    private function attachRole(User $user, string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Build a Submitted booking with its pending BookingApproval row —
     * the post-Submit state SubmitBookingAction would have produced.
     *
     * @return array{booking: Booking, approver: User, requester: User}
     */
    private function makeSubmittedBooking(): array
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $approver = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $this->attachRole($approver, 'unit_approver');
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = Booking::create([
            'booking_code' => 'BKG-20260505-NOTIF',
            'room_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $unit->id,
            'created_by_user_id' => $requester->id,
            'subject' => 'Rapat Evaluasi',
            'attendee_count' => 5,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
            'status' => BookingStatus::Submitted->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'unit_approver',
            'current_approval_step' => 1,
            'current_approver_user_id' => $approver->id,
            'submitted_at' => Carbon::now(),
        ]);

        BookingApproval::create([
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
        ]);

        return [
            'booking' => $booking->fresh(['approvals']) ?? $booking,
            'approver' => $approver,
            'requester' => $requester,
        ];
    }

    // ─── CASCADE ─────────────────────────────────────────────────────

    public function test_submit_notifies_the_assigned_approver(): void
    {
        Notification::fake();

        $unit = Unit::factory()->create();
        $approver = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $this->attachRole($approver, 'unit_approver');
        $requester = User::factory()->create([
            'unit_id' => $unit->id,
            'approver_user_id' => $approver->id,
        ]);
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $this->submitAction->execute($requester, [
            'room_id' => $room->id,
            'subject' => 'Rapat Koordinasi',
            'attendee_count' => 4,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ]);

        Notification::assertSentTo($approver, BookingSubmittedNotification::class);
        Notification::assertCount(1);
    }

    public function test_submit_to_auto_approve_room_sends_no_notification(): void
    {
        Notification::fake();

        $requester = User::factory()->create();
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $this->submitAction->execute($requester, [
            'room_id' => $room->id,
            'subject' => 'Rapat Mandiri',
            'attendee_count' => 2,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ]);

        Notification::assertNothingSent();
    }

    public function test_approve_notifies_the_requester(): void
    {
        Notification::fake();

        $ctx = $this->makeSubmittedBooking();

        $this->approveAction->execute($ctx['booking'], $ctx['approver']);

        Notification::assertSentTo($ctx['requester'], BookingApprovedNotification::class);
        Notification::assertCount(1);
    }

    public function test_reject_notifies_the_requester(): void
    {
        Notification::fake();

        $ctx = $this->makeSubmittedBooking();

        $this->rejectAction->execute(
            $ctx['booking'],
            $ctx['approver'],
            'Ruangan dipakai untuk audit internal.',
        );

        Notification::assertSentTo($ctx['requester'], BookingRejectedNotification::class);
        Notification::assertCount(1);
    }

    // ─── PAYLOAD SHAPE ───────────────────────────────────────────────

    public function test_submitted_notification_payload_shape(): void
    {
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];

        $payload = (new BookingSubmittedNotification($booking))->toArray($ctx['approver']);

        $this->assertSame(NotificationType::BookingSubmitted->value, $payload['type']);
        $this->assertSame($booking->id, $payload['booking_id']);
        $this->assertSame($booking->booking_code, $payload['booking_code']);
        $this->assertSame($booking->subject, $payload['subject']);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('url', $payload);
    }

    public function test_approved_notification_payload_shape(): void
    {
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];

        $payload = (new BookingApprovedNotification($booking))->toArray($ctx['requester']);

        $this->assertSame(NotificationType::BookingApproved->value, $payload['type']);
        $this->assertSame($booking->id, $payload['booking_id']);
        $this->assertSame($booking->booking_code, $payload['booking_code']);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('url', $payload);
    }

    public function test_rejected_notification_payload_includes_reason(): void
    {
        $reason = 'Bentrok dengan agenda direksi.';
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];
        $booking->update(['rejection_reason' => $reason]);

        $payload = (new BookingRejectedNotification($booking))->toArray($ctx['requester']);

        $this->assertSame(NotificationType::BookingRejected->value, $payload['type']);
        $this->assertSame($reason, $payload['reason']);
        $this->assertArrayHasKey('message', $payload);
        $this->assertArrayHasKey('url', $payload);
    }
}
