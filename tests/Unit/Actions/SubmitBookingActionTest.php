<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\SubmitBookingAction;
use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Exceptions\ApprovalRoutingException;
use App\Exceptions\BookingConflictException;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Role;
use App\Models\Room;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Tests for App\Actions\SubmitBookingAction.
 *
 * Covers Phase 2 Piece 3 of Sprint 2B. Validates that the action:
 *  - Routes to correct status based on room.approval_mode
 *  - Resolves approver per Blueprint Bagian H.4
 *  - Throws cleanly when approver cannot be resolved
 *  - Creates all required side effects (approval row, status history, activity log)
 *  - Rolls back the entire transaction on conflict
 *  - Generates valid booking codes
 *
 * @see SubmitBookingAction
 */
class SubmitBookingActionTest extends TestCase
{
    use RefreshDatabase;

    private SubmitBookingAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles + permissions for ga_admin lookup tests
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->action = $this->app->make(SubmitBookingAction::class);

        // Freeze time during typical business hours on a Tuesday
        Carbon::setTestNow('2026-05-05 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Build a valid booking input array, allowing overrides for negative tests.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function bookingInput(Room $room, array $overrides = []): array
    {
        return array_merge([
            'room_id' => $room->id,
            'subject' => 'Rapat Mingguan Tim',
            'agenda' => 'Review sprint progress.',
            'attendee_count' => 5,
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ], $overrides);
    }

    private function attachRole(User $user, string $roleCode): void
    {
        $role = Role::where('code', $roleCode)->firstOrFail();
        $user->roles()->attach($role->id, [
            'is_primary' => true,
            'assigned_at' => now(),
        ]);
    }

    // ─── HAPPY PATHS: STATUS ROUTING ─────────────────────────────────

    public function test_creates_approved_booking_when_room_has_no_approval_mode(): void
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'is_active' => true,
            'status' => 'active',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertSame(BookingStatus::Approved, $booking->status);
        $this->assertNull($booking->current_approval_step);
        $this->assertNull($booking->current_approver_user_id);
        $this->assertNotNull($booking->approved_at);
        $this->assertNotNull($booking->submitted_at);
        $this->assertSame(RoomApprovalMode::None, $booking->approval_mode_snapshot);
    }

    public function test_creates_submitted_booking_when_room_requires_unit_approver(): void
    {
        $unit = Unit::factory()->create();
        $approver = User::factory()->create([
            'unit_id' => $unit->id,
            'is_active' => true,
        ]);
        $requester = User::factory()->create([
            'unit_id' => $unit->id,
            'approver_user_id' => $approver->id,
        ]);
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame(1, $booking->current_approval_step);
        $this->assertSame($approver->id, $booking->current_approver_user_id);
        $this->assertNotNull($booking->submitted_at);
        $this->assertNull($booking->approved_at);
        $this->assertSame(RoomApprovalMode::UnitApprover, $booking->approval_mode_snapshot);
    }

    public function test_creates_submitted_booking_when_room_requires_ga_admin(): void
    {
        $unit = Unit::factory()->create();
        $gaAdmin = User::factory()->create(['is_active' => true]);
        $this->attachRole($gaAdmin, 'ga_admin');

        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $room = Room::factory()->create([
            'approval_mode' => 'ga_admin',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertSame(BookingStatus::Submitted, $booking->status);
        $this->assertSame(1, $booking->current_approval_step);
        $this->assertSame($gaAdmin->id, $booking->current_approver_user_id);
        $this->assertSame(RoomApprovalMode::GaAdmin, $booking->approval_mode_snapshot);
    }

    // ─── ERROR PATHS: APPROVAL ROUTING ───────────────────────────────

    public function test_throws_when_unit_approver_required_but_requester_has_no_approver(): void
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create([
            'unit_id' => $unit->id,
            'approver_user_id' => null,
        ]);
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $this->expectException(ApprovalRoutingException::class);
        $this->expectExceptionMessage('belum memiliki approver');

        $this->action->execute($requester, $this->bookingInput($room));
    }

    public function test_throws_when_ga_admin_mode_but_no_active_ga_admin_exists(): void
    {
        // Note: setUp seeds roles but does NOT create a ga_admin user.
        // So there are zero active users with ga_admin role.
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $room = Room::factory()->create([
            'approval_mode' => 'ga_admin',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $this->expectException(ApprovalRoutingException::class);
        $this->expectExceptionMessage('Tidak ada GA Admin aktif');

        $this->action->execute($requester, $this->bookingInput($room));
    }

    public function test_excludes_inactive_ga_admin_from_approver_pool(): void
    {
        $unit = Unit::factory()->create();
        $inactiveGaAdmin = User::factory()->create(['is_active' => false]);
        $this->attachRole($inactiveGaAdmin, 'ga_admin');

        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $room = Room::factory()->create([
            'approval_mode' => 'ga_admin',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        // Inactive GA admin should NOT be picked; should throw
        $this->expectException(ApprovalRoutingException::class);

        $this->action->execute($requester, $this->bookingInput($room));
    }

    // ─── SIDE EFFECTS: APPROVAL ROW ──────────────────────────────────

    public function test_creates_booking_approval_row_when_approval_required(): void
    {
        $unit = Unit::factory()->create();
        $approver = User::factory()->create(['unit_id' => $unit->id]);
        $requester = User::factory()->create([
            'unit_id' => $unit->id,
            'approver_user_id' => $approver->id,
        ]);
        $room = Room::factory()->create([
            'approval_mode' => 'unit_approver',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertDatabaseHas('booking_approvals', [
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
        ]);
    }

    public function test_does_not_create_approval_row_when_mode_is_none(): void
    {
        $requester = User::factory()->create();
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertDatabaseMissing('booking_approvals', [
            'booking_id' => $booking->id,
        ]);
    }

    // ─── SIDE EFFECTS: STATUS HISTORY + AUDIT ────────────────────────

    public function test_records_status_history_with_null_from_status(): void
    {
        $requester = User::factory()->create();
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertDatabaseHas('booking_status_histories', [
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => 'approved',
            'changed_by_user_id' => $requester->id,
        ]);
    }

    public function test_records_activity_log_entry_with_correct_module_and_event(): void
    {
        $requester = User::factory()->create();
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertDatabaseHas('activity_logs', [
            'actor_user_id' => $requester->id,
            'module' => 'bookings',
            'event' => 'submit',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
        ]);
    }

    // ─── BOOKING CODE FORMAT ─────────────────────────────────────────

    public function test_generates_unique_booking_code_with_correct_format(): void
    {
        $requester = User::factory()->create();
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertMatchesRegularExpression(
            '/^BKG-\d{8}-[A-Z0-9]{4}$/',
            $booking->booking_code
        );
        $this->assertStringStartsWith(
            'BKG-'.Carbon::now()->format('Ymd').'-',
            $booking->booking_code
        );
    }

    // ─── DATA SNAPSHOTS (Dec-04, requester_unit_id) ──────────────────

    public function test_snapshots_requester_unit_id_at_creation(): void
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $booking = $this->action->execute($requester, $this->bookingInput($room));

        $this->assertSame($unit->id, $booking->requester_unit_id);
    }

    // ─── CONFLICT INTEGRATION + TRANSACTION ROLLBACK ─────────────────

    public function test_throws_booking_conflict_when_slot_overlaps_existing(): void
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        // Pre-existing approved booking 10:00-11:00
        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'approved',
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ]);

        $this->expectException(BookingConflictException::class);

        // Try to book overlapping 10:30-11:30
        $this->action->execute($requester, $this->bookingInput($room, [
            'starts_at' => '2026-05-05 10:30:00',
            'ends_at' => '2026-05-05 11:30:00',
        ]));
    }

    public function test_rolls_back_all_writes_when_conflict_detected(): void
    {
        $unit = Unit::factory()->create();
        $requester = User::factory()->create(['unit_id' => $unit->id]);
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        Booking::factory()->create([
            'room_id' => $room->id,
            'status' => 'approved',
            'starts_at' => '2026-05-05 10:00:00',
            'ends_at' => '2026-05-05 11:00:00',
        ]);

        $bookingsBefore = Booking::count();
        $logsBefore = ActivityLog::count();

        try {
            $this->action->execute($requester, $this->bookingInput($room, [
                'starts_at' => '2026-05-05 10:30:00',
                'ends_at' => '2026-05-05 11:30:00',
            ]));
            $this->fail('Expected BookingConflictException was not thrown.');
        } catch (BookingConflictException $e) {
            // expected
        }

        // No booking created, no activity log written
        $this->assertSame($bookingsBefore, Booking::count());
        $this->assertSame($logsBefore, ActivityLog::count());
    }

    public function test_throws_when_room_is_inactive(): void
    {
        $requester = User::factory()->create();
        $room = Room::factory()->create([
            'approval_mode' => 'none',
            'is_active' => false,
            'capacity' => 10,
            'booking_buffer_minutes' => 0,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('tidak tersedia');

        $this->action->execute($requester, $this->bookingInput($room));
    }
}
