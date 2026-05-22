<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\RescheduleBookingAction;
use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Exceptions\BookingConflictException;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingCancelledNotification;
use App\Notifications\BookingSubmittedNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class RescheduleBookingActionTest extends TestCase
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

    public function test_reschedules_an_approved_booking_into_a_new_submitted_booking(): void
    {
        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $roomA = $this->makeRoom(RoomApprovalMode::UnitApprover);
        $roomB = $this->makeRoom(RoomApprovalMode::UnitApprover);

        $a = $this->makeApprovedBooking(
            $roomA, $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        $b = app(RescheduleBookingAction::class)->execute(
            $a,
            $requester,
            $this->newData($roomB, Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
        );

        $this->assertNotSame($a->id, $b->id);
        $this->assertSame(BookingStatus::Submitted, $b->status);
        $this->assertSame($roomB->id, $b->room_id);
        $this->assertSame('Rapat Dijadwalkan Ulang', $b->subject);
    }

    public function test_the_original_booking_is_cancelled(): void
    {
        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $a = $this->makeApprovedBooking(
            $this->makeRoom(RoomApprovalMode::UnitApprover), $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        app(RescheduleBookingAction::class)->execute(
            $a, $requester,
            $this->newData($this->makeRoom(RoomApprovalMode::UnitApprover), Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
        );

        $a->refresh();
        $this->assertSame(BookingStatus::Cancelled, $a->status);
        $this->assertNotNull($a->cancelled_at);
        $this->assertSame('Reservasi dijadwalkan ulang ke jadwal baru.', $a->cancellation_reason);
    }

    public function test_the_new_booking_links_back_to_the_original(): void
    {
        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $a = $this->makeApprovedBooking(
            $this->makeRoom(RoomApprovalMode::UnitApprover), $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        $b = app(RescheduleBookingAction::class)->execute(
            $a, $requester,
            $this->newData($this->makeRoom(RoomApprovalMode::UnitApprover), Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
        );

        $this->assertSame($a->id, $b->rescheduled_from_booking_id);
    }

    public function test_reschedule_into_an_auto_approve_room_creates_an_approved_booking(): void
    {
        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $a = $this->makeApprovedBooking(
            $this->makeRoom(RoomApprovalMode::UnitApprover), $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        $b = app(RescheduleBookingAction::class)->execute(
            $a, $requester,
            $this->newData($this->makeRoom(RoomApprovalMode::None), Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
        );

        $this->assertSame(BookingStatus::Approved, $b->status);
        $this->assertNull($b->current_approver_user_id);
    }

    public function test_reschedule_writes_a_reschedule_activity_log(): void
    {
        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $a = $this->makeApprovedBooking(
            $this->makeRoom(RoomApprovalMode::UnitApprover), $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        $b = app(RescheduleBookingAction::class)->execute(
            $a, $requester,
            $this->newData($this->makeRoom(RoomApprovalMode::UnitApprover), Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
        );

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'reschedule',
            'module' => 'bookings',
            'subject_type' => Booking::class,
            'subject_id' => $b->id,
            'actor_user_id' => $requester->id,
        ]);

        /** @var ActivityLog $log */
        $log = ActivityLog::query()
            ->where('event', 'reschedule')
            ->where('subject_id', $b->id)
            ->firstOrFail();
        $context = $log->getAttribute('context');
        $this->assertIsArray($context);
        $this->assertSame($a->id, $context['rescheduled_from_booking_id']);
        $this->assertSame($a->booking_code, $context['original_booking_code']);
    }

    public function test_reschedule_suppresses_the_originals_cancel_notification(): void
    {
        Notification::fake();

        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $a = $this->makeApprovedBooking(
            $this->makeRoom(RoomApprovalMode::UnitApprover), $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        app(RescheduleBookingAction::class)->execute(
            $a, $requester,
            $this->newData($this->makeRoom(RoomApprovalMode::UnitApprover), Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
        );

        Notification::assertNotSentTo($approver, BookingCancelledNotification::class);
    }

    public function test_reschedule_sends_the_new_bookings_submitted_notification(): void
    {
        Notification::fake();

        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $a = $this->makeApprovedBooking(
            $this->makeRoom(RoomApprovalMode::UnitApprover), $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        app(RescheduleBookingAction::class)->execute(
            $a, $requester,
            $this->newData($this->makeRoom(RoomApprovalMode::UnitApprover), Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
        );

        Notification::assertSentTo($approver, BookingSubmittedNotification::class);
    }

    public function test_refuses_to_reschedule_a_booking_that_is_not_approved(): void
    {
        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $draft = $this->makeDraftBooking(
            $this->makeRoom(RoomApprovalMode::UnitApprover), $requester,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        $this->expectException(DomainException::class);

        app(RescheduleBookingAction::class)->execute(
            $draft, $requester,
            $this->newData($this->makeRoom(RoomApprovalMode::UnitApprover), Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
        );
    }

    public function test_a_conflicting_new_slot_rolls_back_the_whole_reschedule(): void
    {
        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $roomA = $this->makeRoom(RoomApprovalMode::UnitApprover);
        $roomB = $this->makeRoom(RoomApprovalMode::UnitApprover);

        $a = $this->makeApprovedBooking(
            $roomA, $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        // An existing approved booking already holds the target slot in roomB.
        $this->makeApprovedBooking(
            $roomB, $requester, $approver,
            Carbon::parse('2026-05-13 14:00:00'),
            Carbon::parse('2026-05-13 15:00:00'),
        );

        try {
            app(RescheduleBookingAction::class)->execute(
                $a, $requester,
                $this->newData($roomB, Carbon::parse('2026-05-13 14:00:00'), Carbon::parse('2026-05-13 15:00:00')),
            );
            $this->fail('Expected BookingConflictException was not thrown.');
        } catch (BookingConflictException) {
            // expected
        }

        // The whole reschedule rolled back: A is untouched, B never created.
        $a->refresh();
        $this->assertSame(BookingStatus::Approved, $a->status);
        $this->assertNull($a->cancelled_at);
        $this->assertSame(2, Booking::query()->count());
    }

    public function test_reschedule_can_reuse_the_originals_room_at_an_overlapping_time(): void
    {
        $approver = $this->makeApprover();
        $requester = $this->makeRequester($approver);
        $room = $this->makeRoom(RoomApprovalMode::UnitApprover);

        $a = $this->makeApprovedBooking(
            $room, $requester, $approver,
            Carbon::parse('2026-05-12 10:00:00'),
            Carbon::parse('2026-05-12 11:00:00'),
        );

        // Same room, a slot overlapping A's original window. This succeeds
        // only because A is cancelled before B is submitted.
        $b = app(RescheduleBookingAction::class)->execute(
            $a, $requester,
            $this->newData($room, Carbon::parse('2026-05-12 10:30:00'), Carbon::parse('2026-05-12 12:00:00')),
        );

        $this->assertSame(BookingStatus::Submitted, $b->status);
        $this->assertSame($room->id, $b->room_id);
        $a->refresh();
        $this->assertSame(BookingStatus::Cancelled, $a->status);
    }

    // ---- helpers ----

    private function bookingCode(): string
    {
        return 'BKG-'.Carbon::now()->format('Ymd').'-'.Str::upper(Str::random(6));
    }

    private function makeRoom(RoomApprovalMode $mode): Room
    {
        return Room::factory()->create([
            'approval_mode' => $mode->value,
            'status' => 'active',
            'is_active' => true,
            'capacity' => 20,
        ]);
    }

    private function makeApprover(): User
    {
        return User::factory()->create(['is_active' => true]);
    }

    private function makeRequester(User $approver): User
    {
        return User::factory()->create([
            'is_active' => true,
            'approver_user_id' => $approver->id,
        ]);
    }

    private function makeApprovedBooking(Room $room, User $requester, User $approver, Carbon $start, Carbon $end): Booking
    {
        $booking = Booking::create([
            'booking_code' => $this->bookingCode(),
            'room_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $requester->unit_id,
            'created_by_user_id' => $requester->id,
            'subject' => 'Rapat Asli',
            'attendee_count' => 4,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => BookingStatus::Approved->value,
            'source' => 'user',
            'approval_mode_snapshot' => RoomApprovalMode::UnitApprover->value,
            'approved_at' => Carbon::now(),
        ]);

        BookingApproval::create([
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'approved',
            'action_at' => Carbon::now(),
            'acted_by_user_id' => $approver->id,
        ]);

        return $booking->refresh();
    }

    private function makeDraftBooking(Room $room, User $requester, Carbon $start, Carbon $end): Booking
    {
        return Booking::create([
            'booking_code' => $this->bookingCode(),
            'room_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $requester->unit_id,
            'created_by_user_id' => $requester->id,
            'subject' => 'Draf Rapat',
            'attendee_count' => 4,
            'starts_at' => $start,
            'ends_at' => $end,
            'status' => BookingStatus::Draft->value,
            'source' => 'user',
            'approval_mode_snapshot' => RoomApprovalMode::UnitApprover->value,
        ]);
    }

    /**
     * @return array{room_id: int, subject: string, attendee_count: int, starts_at: string, ends_at: string}
     */
    private function newData(Room $room, Carbon $start, Carbon $end): array
    {
        return [
            'room_id' => $room->id,
            'subject' => 'Rapat Dijadwalkan Ulang',
            'attendee_count' => 6,
            'starts_at' => $start->toDateTimeString(),
            'ends_at' => $end->toDateTimeString(),
        ];
    }
}
