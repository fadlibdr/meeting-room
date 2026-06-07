<?php

declare(strict_types=1);

namespace Tests\Feature\Booking;

use App\Actions\ApproveBookingAction;
use App\Actions\RejectBookingAction;
use App\Actions\SubmitBookingAction;
use App\Enums\BookingStatus;
use App\Enums\NotificationType;
use App\Models\AppSetting;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BookingApprovedNotification;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingRejectedNotification;
use App\Notifications\BookingReminderNotification;
use App\Notifications\BookingSubmittedNotification;
use App\Notifications\RoomBlockCreatedNotification;
use App\Services\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
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
            'resource_id' => $room->id,
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

    // ─── MAIL CHANNEL — gated by the notifications.send_email_default toggle ──

    private function setEmailDefault(bool $on): void
    {
        AppSetting::updateOrCreate(
            ['key' => 'notifications.send_email_default'],
            ['value' => $on ? '1' : '0', 'data_type' => 'boolean', 'label' => 'Email default', 'group' => 'notifications', 'is_editable' => true],
        );
        app(SettingsService::class)->forget('notifications.send_email_default');
    }

    /**
     * @return array<int, \Illuminate\Notifications\Notification>
     */
    private function allNotifications(Booking $booking, RoomBlockSchedule $block): array
    {
        return [
            new BookingSubmittedNotification($booking),
            new BookingApprovedNotification($booking),
            new BookingRejectedNotification($booking),
            new BookingCancelledNotification($booking),
            new BookingReminderNotification($booking),
            new RoomBlockCreatedNotification($block, $booking),
        ];
    }

    public function test_via_is_database_only_when_email_toggle_off(): void
    {
        $this->setEmailDefault(false);
        $ctx = $this->makeSubmittedBooking();
        $block = RoomBlockSchedule::factory()->create(['room_id' => $ctx['booking']->resource_id]);

        foreach ($this->allNotifications($ctx['booking'], $block) as $notification) {
            $this->assertInstanceOf(ShouldQueue::class, $notification);
            $this->assertSame(['database'], $notification->via($ctx['requester']));
        }
    }

    public function test_via_includes_mail_when_email_toggle_on(): void
    {
        $this->setEmailDefault(true);
        $ctx = $this->makeSubmittedBooking();
        $block = RoomBlockSchedule::factory()->create(['room_id' => $ctx['booking']->resource_id]);

        foreach ($this->allNotifications($ctx['booking'], $block) as $notification) {
            $this->assertSame(['database', 'mail'], $notification->via($ctx['requester']));
        }
    }

    public function test_submit_routes_on_mail_channel_only_when_toggle_on(): void
    {
        $this->setEmailDefault(true);
        Notification::fake();

        $unit = Unit::factory()->create();
        $approver = User::factory()->create(['unit_id' => $unit->id, 'is_active' => true]);
        $this->attachRole($approver, 'unit_approver');
        $requester = User::factory()->create(['unit_id' => $unit->id, 'approver_user_id' => $approver->id]);
        $room = Room::factory()->create(['approval_mode' => 'unit_approver', 'is_active' => true, 'status' => 'active', 'capacity' => 10, 'booking_buffer_minutes' => 0]);

        $this->submitAction->execute($requester, [
            'room_id' => $room->id, 'subject' => 'Rapat', 'attendee_count' => 3,
            'starts_at' => '2026-05-05 10:00:00', 'ends_at' => '2026-05-05 11:00:00',
        ]);

        Notification::assertSentTo(
            $approver,
            BookingSubmittedNotification::class,
            fn ($notification, array $channels): bool => in_array('mail', $channels, true) && in_array('database', $channels, true),
        );
    }

    public function test_submitted_mail_renders_subject_greeting_and_lines(): void
    {
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];

        $mail = (new BookingSubmittedNotification($booking))->toMail($ctx['approver']);

        $this->assertStringContainsString($booking->booking_code, $mail->subject);
        $this->assertStringContainsString($ctx['approver']->name, (string) $mail->greeting);
        $this->assertTrue(
            collect($mail->introLines)->contains(fn (string $l): bool => str_contains($l, $booking->booking_code)),
            'A mail line should reference the booking code.'
        );
        $this->assertSame(route('bookings.show', $booking->id), $mail->actionUrl);
    }

    public function test_approved_mail_renders_subject_and_action(): void
    {
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];

        $mail = (new BookingApprovedNotification($booking))->toMail($ctx['requester']);

        $this->assertStringContainsString($booking->booking_code, $mail->subject);
        $this->assertStringContainsString('disetujui', strtolower(implode(' ', $mail->introLines)));
        $this->assertSame(route('bookings.show', $booking->id), $mail->actionUrl);
    }

    public function test_rejected_mail_includes_the_reason_line(): void
    {
        $reason = 'Bentrok dengan agenda direksi.';
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];
        $booking->update(['rejection_reason' => $reason]);

        $mail = (new BookingRejectedNotification($booking->fresh() ?? $booking))->toMail($ctx['requester']);

        $this->assertStringContainsString($booking->booking_code, $mail->subject);
        $this->assertTrue(
            collect($mail->introLines)->contains(fn (string $l): bool => str_contains($l, $reason)),
            'The rejection reason should appear in the mail body.'
        );
    }

    public function test_cancelled_mail_includes_the_reason_line(): void
    {
        $reason = 'Rapat dibatalkan oleh pemohon.';
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];
        $booking->update(['cancellation_reason' => $reason]);

        $mail = (new BookingCancelledNotification($booking->fresh() ?? $booking))->toMail($ctx['approver']);

        $this->assertStringContainsString($booking->booking_code, $mail->subject);
        $this->assertTrue(
            collect($mail->introLines)->contains(fn (string $l): bool => str_contains($l, $reason)),
            'The cancellation reason should appear in the mail body.'
        );
    }

    public function test_reminder_mail_references_the_booking(): void
    {
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];

        $mail = (new BookingReminderNotification($booking))->toMail($ctx['requester']);

        $this->assertStringContainsString($booking->booking_code, $mail->subject);
        $this->assertSame(route('bookings.show', $booking->id), $mail->actionUrl);
    }

    public function test_room_block_mail_points_at_the_cancelled_booking(): void
    {
        $ctx = $this->makeSubmittedBooking();
        $booking = $ctx['booking'];
        $block = RoomBlockSchedule::factory()->create(['room_id' => $booking->resource_id]);

        $mail = (new RoomBlockCreatedNotification($block, $booking))->toMail($ctx['requester']);

        $this->assertStringContainsString($booking->booking_code, $mail->subject);
        $this->assertSame(route('bookings.show', $booking->id), $mail->actionUrl);
    }

    // ─── PER-USER PREFERENCE (2.1.2) ─────────────────────────────────

    public function test_user_opt_out_suppresses_mail_even_when_global_is_on(): void
    {
        $this->setEmailDefault(true);
        $ctx = $this->makeSubmittedBooking();
        $ctx['requester']->update(['email_notifications' => false]);
        $block = RoomBlockSchedule::factory()->create(['room_id' => $ctx['booking']->resource_id]);
        $user = $ctx['requester']->fresh();

        foreach ($this->allNotifications($ctx['booking'], $block) as $notification) {
            $this->assertSame(['database'], $notification->via($user));
        }
    }

    public function test_user_opt_in_with_global_on_includes_mail(): void
    {
        $this->setEmailDefault(true);
        $ctx = $this->makeSubmittedBooking();
        $ctx['requester']->update(['email_notifications' => true]);

        $via = (new BookingApprovedNotification($ctx['booking']))->via($ctx['requester']->fresh());

        $this->assertSame(['database', 'mail'], $via);
    }

    public function test_user_pref_is_irrelevant_when_global_is_off(): void
    {
        $this->setEmailDefault(false);
        $ctx = $this->makeSubmittedBooking();
        $ctx['requester']->update(['email_notifications' => true]);

        $via = (new BookingApprovedNotification($ctx['booking']))->via($ctx['requester']->fresh());

        $this->assertSame(['database'], $via);
    }

    public function test_default_user_inherits_global_on(): void
    {
        $this->setEmailDefault(true);
        $ctx = $this->makeSubmittedBooking(); // requester created with the column default (true)

        $via = (new BookingApprovedNotification($ctx['booking']))->via($ctx['requester']);

        $this->assertSame(['database', 'mail'], $via);
    }
}
