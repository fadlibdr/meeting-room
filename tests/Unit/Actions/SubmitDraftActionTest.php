<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\SubmitDraftAction;
use App\Enums\BookingStatus;
use App\Exceptions\ApprovalRoutingException;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use App\Notifications\BookingSubmittedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Unit tests for SubmitDraftAction (M3-C2-ii) — the Draft -> Submitted
 * transition. Routing itself is covered by ApprovalRoutingServiceTest;
 * these cover the transition pipeline: the Draft guard, conflict re-check,
 * the booking mutation, approval row, history, audit, and notification.
 *
 * @see SubmitDraftAction
 */
class SubmitDraftActionTest extends TestCase
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

    private function action(): SubmitDraftAction
    {
        return app(SubmitDraftAction::class);
    }

    private function attachRole(User $user, string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);
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

    private function makeRequester(?User $approver = null): User
    {
        $unit = Unit::factory()->create();

        return User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
            'approver_user_id' => $approver?->id,
        ]);
    }

    private function bookingCode(): string
    {
        return 'BKG-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    private function makeDraft(
        User $owner,
        Room $room,
        string $startsAt = '2026-05-06 10:00:00',
        string $endsAt = '2026-05-06 11:00:00',
        string $approvalSnapshot = 'unit_approver',
    ): Booking {
        return Booking::create([
            'booking_code' => $this->bookingCode(),
            'room_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Draft',
            'agenda' => 'Agenda draft.',
            'attendee_count' => 4,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => BookingStatus::Draft->value,
            'source' => 'user',
            'approval_mode_snapshot' => $approvalSnapshot,
        ]);
    }

    private function makeApprovedBooking(Room $room, string $startsAt, string $endsAt): Booking
    {
        $owner = $this->makeRequester();

        return Booking::create([
            'booking_code' => $this->bookingCode(),
            'room_id' => $room->id,
            'requester_user_id' => $owner->id,
            'requester_unit_id' => $owner->unit_id,
            'created_by_user_id' => $owner->id,
            'subject' => 'Rapat Existing',
            'attendee_count' => 4,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => BookingStatus::Approved->value,
            'source' => 'user',
            'approval_mode_snapshot' => 'none',
            'approved_at' => Carbon::now(),
        ]);
    }

    // ─── routing ────────────────────────────────────────────────────

    public function test_submits_a_draft_to_a_unit_approver_room(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeDraft($owner, $this->makeRoom('unit_approver'));

        $result = $this->action()->execute($draft, $owner);

        $this->assertSame(BookingStatus::Submitted, $result->status);
        $this->assertSame(1, $result->current_approval_step);
        $this->assertSame($approver->id, $result->current_approver_user_id);
        $this->assertNotNull($result->submitted_at);
        $this->assertNull($result->approved_at);
    }

    public function test_submits_a_draft_to_an_auto_approve_room(): void
    {
        $owner = $this->makeRequester();
        $draft = $this->makeDraft($owner, $this->makeRoom('none'));

        $result = $this->action()->execute($draft, $owner);

        $this->assertSame(BookingStatus::Approved, $result->status);
        $this->assertNull($result->current_approval_step);
        $this->assertNull($result->current_approver_user_id);
        $this->assertNotNull($result->approved_at);
    }

    public function test_submits_a_draft_to_a_ga_admin_room(): void
    {
        $gaAdmin = $this->makeRequester();
        $this->attachRole($gaAdmin, 'ga_admin');
        $owner = $this->makeRequester();
        $draft = $this->makeDraft($owner, $this->makeRoom('ga_admin'));

        $result = $this->action()->execute($draft, $owner);

        $this->assertSame(BookingStatus::Submitted, $result->status);
        $this->assertSame($gaAdmin->id, $result->current_approver_user_id);
    }

    // ─── approval row ───────────────────────────────────────────────

    public function test_creates_a_pending_approval_row_when_approval_required(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeDraft($owner, $this->makeRoom('unit_approver'));

        $this->action()->execute($draft, $owner);

        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $draft->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
        ]);
    }

    public function test_does_not_create_an_approval_row_for_an_auto_approve_room(): void
    {
        $owner = $this->makeRequester();
        $draft = $this->makeDraft($owner, $this->makeRoom('none'));

        $this->action()->execute($draft, $owner);

        $this->assertDatabaseMissing('booking_approvals', [
            'booking_id' => $draft->id,
        ]);
    }

    // ─── history / audit / snapshot ─────────────────────────────────

    public function test_writes_a_draft_to_submitted_status_history(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeDraft($owner, $this->makeRoom('unit_approver'));

        $this->action()->execute($draft, $owner);

        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $draft->id,
            'from_status' => BookingStatus::Draft->value,
            'to_status' => BookingStatus::Submitted->value,
            'changed_by_user_id' => $owner->id,
        ]);
    }

    public function test_writes_an_activity_log_for_the_submit_event(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeDraft($owner, $this->makeRoom('unit_approver'));

        $this->action()->execute($draft, $owner);

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'bookings',
            'event' => 'submit',
            'subject_type' => Booking::class,
            'subject_id' => $draft->id,
            'actor_user_id' => $owner->id,
        ]);
    }

    public function test_re_snapshots_the_approval_mode_at_submit_time(): void
    {
        // Draft carries a stale 'none' snapshot; the room is now unit_approver.
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeDraft(
            $owner,
            $this->makeRoom('unit_approver'),
            approvalSnapshot: 'none',
        );

        $result = $this->action()->execute($draft, $owner);

        $this->assertSame('unit_approver', $result->approval_mode_snapshot->value);
    }

    public function test_records_the_actor_as_updated_by(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeDraft($owner, $this->makeRoom('unit_approver'));

        $result = $this->action()->execute($draft, $owner);

        $this->assertSame($owner->id, $result->updated_by_user_id);
    }

    // ─── guards ─────────────────────────────────────────────────────

    public function test_refuses_to_submit_a_booking_that_is_not_draft(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeDraft($owner, $this->makeRoom('unit_approver'));
        $draft->update(['status' => BookingStatus::Submitted->value]);

        $this->expectException(DomainException::class);

        $this->action()->execute($draft, $owner);
    }

    public function test_throws_when_the_room_is_inactive(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $room = $this->makeRoom('unit_approver');
        $draft = $this->makeDraft($owner, $room);
        $room->update(['status' => 'archived', 'is_active' => false]);

        $this->expectException(DomainException::class);

        $this->action()->execute($draft, $owner);
    }

    public function test_throws_when_unit_approver_required_but_requester_has_none(): void
    {
        $owner = $this->makeRequester(); // no approver_user_id
        $draft = $this->makeDraft($owner, $this->makeRoom('unit_approver'));

        $this->expectException(ApprovalRoutingException::class);

        $this->action()->execute($draft, $owner);
    }

    // ─── conflict ───────────────────────────────────────────────────

    public function test_throws_a_conflict_when_the_slot_is_taken(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $room = $this->makeRoom('unit_approver');
        $draft = $this->makeDraft($owner, $room);
        $this->makeApprovedBooking($room, '2026-05-06 10:00:00', '2026-05-06 11:00:00');

        $this->expectException(BookingConflictException::class);

        $this->action()->execute($draft, $owner);
    }

    public function test_rolls_back_all_writes_when_a_conflict_is_detected(): void
    {
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $room = $this->makeRoom('unit_approver');
        $draft = $this->makeDraft($owner, $room);
        $this->makeApprovedBooking($room, '2026-05-06 10:00:00', '2026-05-06 11:00:00');

        try {
            $this->action()->execute($draft, $owner);
        } catch (BookingConflictException) {
            // expected
        }

        $draft->refresh();
        $this->assertSame(BookingStatus::Draft, $draft->status);
        $this->assertDatabaseMissing('booking_approvals', ['booking_id' => $draft->id]);
        $this->assertDatabaseMissing('booking_status_histories', [
            'booking_id' => $draft->id,
            'to_status' => BookingStatus::Submitted->value,
        ]);
    }

    // ─── notification ───────────────────────────────────────────────

    public function test_notifies_the_assigned_approver(): void
    {
        Notification::fake();
        $approver = $this->makeRequester();
        $owner = $this->makeRequester($approver);
        $draft = $this->makeDraft($owner, $this->makeRoom('unit_approver'));

        $this->action()->execute($draft, $owner);

        Notification::assertSentTo($approver, BookingSubmittedNotification::class);
    }

    public function test_auto_approve_submit_sends_no_notification(): void
    {
        Notification::fake();
        $owner = $this->makeRequester();
        $draft = $this->makeDraft($owner, $this->makeRoom('none'));

        $this->action()->execute($draft, $owner);

        Notification::assertNothingSent();
    }
}
