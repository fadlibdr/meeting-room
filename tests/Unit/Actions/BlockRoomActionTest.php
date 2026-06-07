<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\BlockRoomAction;
use App\Enums\BookingStatus;
use App\Enums\RoomBlockType;
use App\Exceptions\RoomBlockConflictException;
use App\Models\Booking;
use App\Models\Room;
use App\Models\RoomBlockSchedule;
use App\Models\User;
use App\Notifications\RoomBlockCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Tests\TestCase;

class BlockRoomActionTest extends TestCase
{
    use RefreshDatabase;

    private function action(): BlockRoomAction
    {
        return app(BlockRoomAction::class);
    }

    private function bookingAt(Room $room, string $start, string $end, BookingStatus $status): Booking
    {
        return Booking::factory()->create([
            'resource_id' => $room->id,
            'status' => $status,
            'starts_at' => Carbon::parse($start),
            'ends_at' => Carbon::parse($end),
        ]);
    }

    public function test_creates_a_block_when_no_conflicts(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();

        $block = $this->action()->execute(
            $room,
            $actor,
            RoomBlockType::Maintenance,
            'Pemeliharaan AC',
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 12:00:00'),
            reason: 'Servis rutin',
        );

        $this->assertTrue($block->exists);
        $this->assertSame(RoomBlockType::Maintenance, $block->block_type);
        $this->assertSame($actor->id, $block->created_by_user_id);
        $this->assertTrue($block->is_active);
        $this->assertSame(1, RoomBlockSchedule::where('room_id', $room->id)->count());
    }

    public function test_throws_conflict_when_bookings_overlap_and_not_forced(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $booking = $this->bookingAt($room, '2026-06-01 10:00:00', '2026-06-01 11:00:00', BookingStatus::Approved);

        try {
            $this->action()->execute(
                $room,
                $actor,
                RoomBlockType::Maintenance,
                'Pemeliharaan',
                Carbon::parse('2026-06-01 09:00:00'),
                Carbon::parse('2026-06-01 12:00:00'),
            );
            $this->fail('Expected RoomBlockConflictException.');
        } catch (RoomBlockConflictException $e) {
            $this->assertCount(1, $e->conflicts);
        }

        $this->assertSame(0, RoomBlockSchedule::count());
        $this->assertSame(BookingStatus::Approved, $booking->refresh()->status);
    }

    public function test_forced_block_cancels_conflicting_bookings(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $booking = $this->bookingAt($room, '2026-06-01 10:00:00', '2026-06-01 11:00:00', BookingStatus::Approved);

        $block = $this->action()->execute(
            $room,
            $actor,
            RoomBlockType::Cleaning,
            'Pembersihan Mendalam',
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 12:00:00'),
            cancelConflictingBookings: true,
        );

        $this->assertTrue($block->exists);
        $this->assertSame(BookingStatus::Cancelled, $booking->refresh()->status);
    }

    public function test_forced_cancellation_notifies_the_requester(): void
    {
        Notification::fake();

        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $requester = User::factory()->create();
        $booking = Booking::factory()->create([
            'resource_id' => $room->id,
            'requester_user_id' => $requester->id,
            'status' => BookingStatus::Approved,
            'starts_at' => Carbon::parse('2026-06-01 10:00:00'),
            'ends_at' => Carbon::parse('2026-06-01 11:00:00'),
        ]);

        $this->action()->execute(
            $room,
            $actor,
            RoomBlockType::Maintenance,
            'Pemeliharaan',
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 12:00:00'),
            cancelConflictingBookings: true,
        );

        Notification::assertSentTo($requester, RoomBlockCreatedNotification::class);
    }

    public function test_ignores_non_locking_bookings(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $draft = $this->bookingAt($room, '2026-06-01 10:00:00', '2026-06-01 11:00:00', BookingStatus::Draft);

        $block = $this->action()->execute(
            $room,
            $actor,
            RoomBlockType::Maintenance,
            'Pemeliharaan',
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 12:00:00'),
        );

        $this->assertTrue($block->exists);
        $this->assertSame(BookingStatus::Draft, $draft->refresh()->status);
    }

    public function test_ignores_non_overlapping_bookings(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();
        $this->bookingAt($room, '2026-06-01 14:00:00', '2026-06-01 15:00:00', BookingStatus::Approved);

        $block = $this->action()->execute(
            $room,
            $actor,
            RoomBlockType::Maintenance,
            'Pemeliharaan',
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 12:00:00'),
        );

        $this->assertTrue($block->exists);
    }

    public function test_writes_activity_log_for_block_create(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();

        $block = $this->action()->execute(
            $room,
            $actor,
            RoomBlockType::Reserved,
            'Dicadangkan Direksi',
            Carbon::parse('2026-06-01 09:00:00'),
            Carbon::parse('2026-06-01 12:00:00'),
        );

        $this->assertDatabaseHas('activity_logs', [
            'module' => 'rooms',
            'event' => 'block-create',
            'subject_type' => RoomBlockSchedule::class,
            'subject_id' => $block->id,
            'actor_user_id' => $actor->id,
        ]);
    }

    public function test_throws_when_end_is_not_after_start(): void
    {
        $room = Room::factory()->create();
        $actor = User::factory()->create();

        $this->expectException(InvalidArgumentException::class);

        $this->action()->execute(
            $room,
            $actor,
            RoomBlockType::Maintenance,
            'Pemeliharaan',
            Carbon::parse('2026-06-01 12:00:00'),
            Carbon::parse('2026-06-01 09:00:00'),
        );
    }
}
