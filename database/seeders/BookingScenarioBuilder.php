<?php

namespace Database\Seeders;

use App\Enums\BookingStatus;
use App\Enums\RoomApprovalMode;
use App\Models\Booking;
use App\Models\BookingApproval;
use App\Models\BookingStatusHistory;
use App\Models\Room;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Builds a single booking + its approvals + status history in one go.
 * Encapsulates Dec-03 (hybrid pointer) and Dec-04 (string snapshot) correctly.
 */
class BookingScenarioBuilder
{
    private static int $sequence = 1;

    public static function create(
        Room $room,
        User $requester,
        User $approver,
        ?User $gaAdmin,
        BookingStatus $targetStatus,
        CarbonImmutable $startsAt,
        int $durationHours = 2,
        ?string $subjectOverride = null,
    ): Booking {
        $endsAt = $startsAt->addHours($durationHours);
        $code = self::generateCode($startsAt);
        $modeSnapshot = $room->approval_mode;

        // Always create as draft first, then advance
        $booking = Booking::create([
            'booking_code' => $code,
            'room_id' => $room->id,
            'requester_user_id' => $requester->id,
            'requester_unit_id' => $requester->unit_id,
            'created_by_user_id' => $requester->id,
            'updated_by_user_id' => null,
            'subject' => $subjectOverride ?? self::randomSubject(),
            'agenda' => 'Pembahasan terkait '.($subjectOverride ?? 'agenda rapat').'.',
            'attendee_count' => fake()->numberBetween(2, $room->capacity),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'status' => BookingStatus::Draft,
            'source' => 'user',
            'approval_mode_snapshot' => $modeSnapshot,
            'current_approval_step' => null,
            'current_approver_user_id' => null,
            'submitted_at' => null,
            'approved_at' => null,
            'rejected_at' => null,
            'cancelled_at' => null,
            'completed_at' => null,
        ]);

        self::recordHistory($booking, null, BookingStatus::Draft->value, $requester->id, 'Booking dibuat');

        // Build the approval chain based on Dec-04 snapshot
        $chain = self::buildApprovalChain($booking, $modeSnapshot, $approver, $gaAdmin);

        // Advance to target status
        match ($targetStatus) {
            BookingStatus::Draft => null,
            BookingStatus::Submitted => self::advanceToSubmitted($booking, $chain, $requester),
            BookingStatus::Approved => self::advanceToApproved($booking, $chain, $requester),
            BookingStatus::Rejected => self::advanceToRejected($booking, $chain, $requester),
            BookingStatus::Cancelled => self::advanceToCancelled($booking, $chain, $requester),
            BookingStatus::Completed => self::advanceToCompleted($booking, $chain, $requester),
        };

        return $booking->fresh();
    }

    /**
     * @return list<BookingApproval>
     */
    private static function buildApprovalChain(
        Booking $booking,
        RoomApprovalMode $mode,
        User $unitApprover,
        ?User $gaAdmin,
    ): array {
        if ($mode === RoomApprovalMode::None) {
            return [];
        }

        $approver = $mode === RoomApprovalMode::GaAdmin
            ? ($gaAdmin ?? throw new \RuntimeException('GA admin required for GaAdmin approval mode'))
            : $unitApprover;

        $approval = BookingApproval::create([
            'booking_id' => $booking->id,
            'sequence_no' => 1,
            'approver_user_id' => $approver->id,
            'status' => 'pending',
            'action_at' => null,
            'action_notes' => null,
            'acted_by_user_id' => null,
        ]);

        return [$approval];
    }

    private static function advanceToSubmitted(Booking $booking, array $chain, User $actor): void
    {
        if (empty($chain)) {
            // No approval needed → straight to approved
            self::advanceToApproved($booking, $chain, $actor);

            return;
        }

        // Dec-03 hybrid pointer: step + user_id
        $firstApproval = $chain[0];
        $booking->update([
            'status' => BookingStatus::Submitted,
            'submitted_at' => now(),
            'current_approval_step' => $firstApproval->sequence_no,
            'current_approver_user_id' => $firstApproval->approver_user_id,
        ]);

        self::recordHistory($booking, BookingStatus::Draft->value, BookingStatus::Submitted->value, $actor->id, 'Booking diajukan untuk approval');
    }

    private static function advanceToApproved(Booking $booking, array $chain, User $actor): void
    {
        if (! empty($chain)) {
            // Submit first
            self::advanceToSubmitted($booking, $chain, $actor);

            // Then approve
            $firstApproval = $chain[0];
            $firstApproval->update([
                'status' => 'approved',
                'action_at' => now(),
                'action_notes' => 'Disetujui',
                'acted_by_user_id' => $firstApproval->approver_user_id,
            ]);

            self::recordHistory($booking, BookingStatus::Submitted->value, BookingStatus::Approved->value, $firstApproval->approver_user_id, 'Disetujui');
        } else {
            // No approval needed; came from draft
            self::recordHistory($booking, BookingStatus::Draft->value, BookingStatus::Approved->value, $actor->id, 'Auto-approved (no approval required)');
        }

        $booking->update([
            'status' => BookingStatus::Approved,
            'approved_at' => now(),
            'current_approval_step' => null,
            'current_approver_user_id' => null,
        ]);
    }

    private static function advanceToRejected(Booking $booking, array $chain, User $actor): void
    {
        if (empty($chain)) {
            // No approval mode shouldn't be rejected; but defensive
            return;
        }

        self::advanceToSubmitted($booking, $chain, $actor);

        $firstApproval = $chain[0];
        $firstApproval->update([
            'status' => 'rejected',
            'action_at' => now(),
            'action_notes' => 'Konflik dengan jadwal lain',
            'acted_by_user_id' => $firstApproval->approver_user_id,
        ]);

        $booking->update([
            'status' => BookingStatus::Rejected,
            'rejected_at' => now(),
            'rejection_reason' => 'Konflik dengan jadwal lain',
            'current_approval_step' => null,
            'current_approver_user_id' => null,
        ]);

        self::recordHistory($booking, BookingStatus::Submitted->value, BookingStatus::Rejected->value, $firstApproval->approver_user_id, 'Ditolak');
    }

    private static function advanceToCancelled(Booking $booking, array $chain, User $actor): void
    {
        $previousStatus = empty($chain) ? BookingStatus::Draft : BookingStatus::Submitted;
        if (! empty($chain)) {
            self::advanceToSubmitted($booking, $chain, $actor);
        }

        $booking->update([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => 'Acara ditunda',
            'current_approval_step' => null,
            'current_approver_user_id' => null,
        ]);

        self::recordHistory($booking, $previousStatus->value, BookingStatus::Cancelled->value, $actor->id, 'Dibatalkan oleh requester');
    }

    private static function advanceToCompleted(Booking $booking, array $chain, User $actor): void
    {
        self::advanceToApproved($booking, $chain, $actor);

        $booking->update([
            'status' => BookingStatus::Completed,
            'completed_at' => now(),
        ]);

        self::recordHistory($booking, BookingStatus::Approved->value, BookingStatus::Completed->value, null, 'Auto-completed (past end time)');
    }

    private static function recordHistory(Booking $booking, ?string $from, string $to, ?int $actorId, string $reason): void
    {
        BookingStatusHistory::create([
            'booking_id' => $booking->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by_user_id' => $actorId,
            'change_reason' => $reason,
            'changed_at' => now(),
        ]);
    }

    private static function generateCode(CarbonImmutable $date): string
    {
        $datePart = $date->format('Ymd');
        $seq = str_pad((string) self::$sequence++, 4, '0', STR_PAD_LEFT);

        return "BKG-{$datePart}-{$seq}";
    }

    private static function randomSubject(): string
    {
        $subjects = [
            'Rapat Koordinasi Mingguan',
            'Diskusi Strategi Q4',
            'Briefing Tim Operasional',
            'Sosialisasi Kebijakan Baru',
            'Workshop Peningkatan Kompetensi',
            'Rapat Anggaran Tahunan',
            'Town Hall Direksi',
            'Presentasi Vendor IT',
            'Review Kinerja Tahunan',
            'Pembahasan Laporan Audit',
            'Rapat Pembukaan Cabang Baru',
            'Diskusi Roadmap Produk',
        ];

        return $subjects[array_rand($subjects)];
    }
}
