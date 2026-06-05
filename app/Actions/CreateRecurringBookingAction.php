<?php

declare(strict_types=1);

namespace App\Actions;

use App\DataTransferObjects\ConflictItem;
use App\Enums\BookingStatus;
use App\Enums\RecurrenceFrequency;
use App\Exceptions\BookingConflictException;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\BookingSubmittedNotification;
use App\Services\RecurrenceExpander;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Creates a recurring booking series by materialising each occurrence as a
 * real booking via SubmitBookingAction (so every occurrence gets the full
 * conflict check, approval routing, history and audit trail). Occurrences
 * that conflict are SKIPPED and reported — the rest are created — so one bad
 * date never blocks the whole series.
 *
 * All created bookings share a self-referential recurrence_group_id pointing
 * at the first (anchor) occurrence.
 */
final class CreateRecurringBookingAction
{
    public function __construct(
        private readonly SubmitBookingAction $submitBooking,
        private readonly RecurrenceExpander $expander,
    ) {}

    /**
     * @param  array{room_id: int, subject: string, agenda?: ?string, attendee_count: int, starts_at: string, ends_at: string, source?: string}  $input
     * @return array{created: Collection<int, Booking>, skipped: list<array{starts_at: string, reason: string}>}
     */
    public function execute(
        User $requester,
        array $input,
        RecurrenceFrequency $frequency,
        int $interval = 1,
        ?CarbonImmutable $until = null,
        ?int $count = null,
        bool $notify = true,
    ): array {
        $occurrences = $this->expander->expand(
            CarbonImmutable::parse($input['starts_at']),
            CarbonImmutable::parse($input['ends_at']),
            $frequency,
            $interval,
            $until,
            $count,
        );

        /** @var Collection<int, Booking> $created */
        $created = collect();
        $skipped = [];

        foreach ($occurrences as $occ) {
            $occInput = $input;
            $occInput['starts_at'] = $occ['starts_at']->format('Y-m-d H:i:s');
            $occInput['ends_at'] = $occ['ends_at']->format('Y-m-d H:i:s');

            try {
                $created->push($this->submitBooking->execute($requester, $occInput, notify: false));
            } catch (BookingConflictException $e) {
                $skipped[] = [
                    'starts_at' => $occ['starts_at']->format('Y-m-d H:i'),
                    'reason' => $this->summarise($e->conflicts),
                ];
            }
        }

        if ($created->isNotEmpty()) {
            /** @var Booking $anchor */
            $anchor = $created->first();

            Booking::query()
                ->whereIn('id', $created->pluck('id')->all())
                ->update(['recurrence_group_id' => $anchor->id]);
            $created->each(static fn (Booking $b) => $b->recurrence_group_id = $anchor->id);

            // One signal per series rather than one per occurrence (avoids inbox
            // spam); every occurrence still appears in the approver's queue.
            if ($notify
                && $anchor->status === BookingStatus::Submitted
                && $anchor->current_approver_user_id !== null) {
                User::findOrFail($anchor->current_approver_user_id)
                    ->notify(new BookingSubmittedNotification($anchor));
            }
        }

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  Collection<int, ConflictItem>  $conflicts
     */
    private function summarise(Collection $conflicts): string
    {
        $reasons = $conflicts
            ->map(static fn (ConflictItem $c): string => match ($c->type) {
                ConflictItem::TYPE_BOOKING => 'bentrok dengan reservasi lain',
                ConflictItem::TYPE_BLOCK => 'ruang sedang diblokir',
                ConflictItem::TYPE_OPERATING_HOURS => 'di luar jam operasional',
                default => 'bentrok',
            })
            ->unique()
            ->values();

        return ucfirst($reasons->implode(', '));
    }
}
