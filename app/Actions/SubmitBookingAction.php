<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Enums\RoomStatus;
use App\Http\Requests\Booking\StoreBookingRequest;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\Room;
use App\Models\User;
use App\Notifications\BookingSubmittedNotification;
use App\Policies\BookingPolicy;
use App\Services\ApprovalRoutingService;
use App\Services\BookingConflictService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Submits a new booking through the full submit pipeline:
 *
 *  1. Lock target room row (lockForUpdate) for race safety
 *  2. Defense-in-depth re-check that room is still active
 *  3. Re-check conflicts inside the lock window via BookingConflictService
 *  4. Resolve approval routing (mode + approver) per Blueprint Bagian H.4
 *  5. Create booking with appropriate status (Dec-03 hybrid pointer)
 *  6. Create booking_approvals row when approval required
 *  7. Record booking_status_histories transition
 *  8. Record activity_logs audit entry
 *
 * Everything wrapped in a single DB::transaction. On ANY failure
 * the entire write set is rolled back — the database never sees a
 * partial submit.
 *
 * @see BookingConflictService
 * @see BookingPolicy
 * @see StoreBookingRequest
 */
final class SubmitBookingAction
{
    public function __construct(
        private readonly BookingConflictService $conflictService,
        private readonly ApprovalRoutingService $routingService,
    ) {}

    /**
     * @param  array{
     *     room_id: int,
     *     subject: string,
     *     agenda?: ?string,
     *     attendee_count: int,
     *     starts_at: string,
     *     ends_at: string,
     *     source?: string
     * }  $input
     */
    public function execute(User $requester, array $input): Booking
    {
        /** @var Booking $booking */
        $booking = DB::transaction(function () use ($requester, $input): Booking {
            return $this->performSubmit($requester, $input);
        });

        // 2D-F: notify the assigned approver — only when the booking resolved
        // to Submitted (auto-approved bookings have no approver, and the
        // requester is already present). After the transaction, by FK id.
        if ($booking->status === BookingStatus::Submitted
            && $booking->current_approver_user_id !== null) {
            User::findOrFail($booking->current_approver_user_id)
                ->notify(new BookingSubmittedNotification($booking));
        }

        return $booking;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function performSubmit(User $requester, array $input): Booking
    {
        // 1. Lock the room row
        /** @var Room $room */
        $room = Room::query()
            ->lockForUpdate()
            ->findOrFail($input['room_id']);

        // 2. Defense-in-depth: room must still be active.
        // status is cast to RoomStatus enum — compare against enum, not string.
        if (! $room->is_active || $room->status !== RoomStatus::Active) {
            throw new DomainException(
                'Ruangan tidak tersedia untuk pemesanan saat ini.'
            );
        }

        // 3. Re-check conflicts inside lock window
        $startsAt = CarbonImmutable::parse($input['starts_at'])->utc();
        $endsAt = CarbonImmutable::parse($input['ends_at'])->utc();
        $this->conflictService->assertNoConflict($room, $startsAt, $endsAt);

        // 4. Resolve approval routing — approval_mode is also an enum cast
        $resolution = $this->routingService->resolve($requester, $room->approval_mode);

        // 5. Create booking row
        $booking = Booking::create([
            'booking_code' => $this->generateBookingCode(),
            'room_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $requester->unit_id,
            'created_by_user_id' => $requester->id,
            'subject' => $input['subject'],
            'agenda' => $input['agenda'] ?? null,
            'attendee_count' => $input['attendee_count'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => $resolution['status']->value,
            'source' => $input['source'] ?? 'user',
            'approval_mode_snapshot' => $room->approval_mode->value,
            'current_approval_step' => $resolution['current_step'],
            'current_approver_user_id' => $resolution['approver_user_id'],
            'submitted_at' => Carbon::now(),
            'approved_at' => $resolution['approved_at'],
        ]);

        // 6. Create approval row if needed
        if ($resolution['approver_user_id'] !== null) {
            BookingApproval::create([
                'booking_id' => $booking->id,
                'sequence_no' => 1,
                'approver_user_id' => $resolution['approver_user_id'],
                'status' => 'pending',
            ]);
        }

        // 7. Status history
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => $resolution['status']->value,
            'changed_by_user_id' => $requester->id,
            'changed_at' => Carbon::now(),
        ]);

        // 8. Audit log
        ActivityLog::create([
            'actor_user_id' => $requester->id,
            'module' => 'bookings',
            'event' => 'submit',
            'subject_type' => Booking::class,
            'subject_id' => $booking->id,
            'description' => sprintf(
                'Booking %s dibuat dengan status %s.',
                $booking->booking_code,
                $resolution['status']->value
            ),
            'context' => [
                'approval_mode' => $room->approval_mode->value,
                'room_id' => $room->id,
                'auto_approved' => $room->approval_mode === RoomApprovalMode::None,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
            ],
        ]);

        return $booking->fresh(['room', 'requester', 'currentApprover', 'approvals']) ?? $booking;
    }

    /**
     * Generate a unique human-readable booking code.
     * Format: BKG-YYYYMMDD-XXXX
     */
    private function generateBookingCode(): string
    {
        $datePart = Carbon::now()->format('Ymd');

        do {
            $randomPart = Str::upper(Str::random(4));
            $code = "BKG-{$datePart}-{$randomPart}";
        } while (Booking::query()->where('booking_code', $code)->exists());

        return $code;
    }
}
