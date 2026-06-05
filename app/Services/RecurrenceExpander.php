<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RecurrenceFrequency;
use Carbon\CarbonImmutable;

/**
 * Expands a recurrence rule into concrete occurrence windows.
 *
 * Each occurrence preserves the original time-of-day and duration. The first
 * occurrence is always the supplied start. Monthly recurrence keeps the same
 * day-of-month and SKIPS months where that day does not exist (e.g. the 31st
 * in February) rather than silently shifting the date.
 *
 * Materialising occurrences as real rows is the project's chosen model
 * (bookings/room_block_schedules carry a self-referential recurrence_group_id),
 * so the caller turns each returned window into a real booking/block.
 */
final class RecurrenceExpander
{
    /** Hard ceiling on how many occurrences a single series may produce. */
    public const MAX_OCCURRENCES = 100;

    /**
     * @param  CarbonImmutable  $start  first occurrence start
     * @param  CarbonImmutable  $end  first occurrence end (defines the duration)
     * @param  int  $interval  every N units (>= 1)
     * @param  CarbonImmutable|null  $until  inclusive last date a start may fall on
     * @param  int|null  $count  total occurrences to produce (including the first)
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable}>
     */
    public function expand(
        CarbonImmutable $start,
        CarbonImmutable $end,
        RecurrenceFrequency $frequency,
        int $interval = 1,
        ?CarbonImmutable $until = null,
        ?int $count = null,
    ): array {
        $interval = max(1, $interval);
        $durationSeconds = (int) $start->diffInSeconds($end);
        $limit = $count !== null ? min($count, self::MAX_OCCURRENCES) : self::MAX_OCCURRENCES;

        $occurrences = [];
        // Step index — bounded well above the occurrence cap so monthly day-skips
        // can never spin forever.
        for ($step = 0; count($occurrences) < $limit && $step <= self::MAX_OCCURRENCES * 4; $step++) {
            $occStart = $this->advance($start, $frequency, $interval * $step);

            if ($occStart === null) {
                continue; // monthly day-of-month does not exist this step
            }

            if ($until !== null && $occStart->greaterThan($until)) {
                break;
            }

            $occurrences[] = [
                'starts_at' => $occStart,
                'ends_at' => $occStart->addSeconds($durationSeconds),
            ];
        }

        return $occurrences;
    }

    private function advance(CarbonImmutable $start, RecurrenceFrequency $frequency, int $steps): ?CarbonImmutable
    {
        if ($steps === 0) {
            return $start;
        }

        return match ($frequency) {
            RecurrenceFrequency::Daily => $start->addDays($steps),
            RecurrenceFrequency::Weekly => $start->addWeeks($steps),
            RecurrenceFrequency::Monthly => $this->addMonthsStrict($start, $steps),
        };
    }

    /**
     * Add whole months while preserving the day-of-month. Returns null when the
     * target month is shorter than the original day (so the occurrence is skipped).
     */
    private function addMonthsStrict(CarbonImmutable $start, int $months): ?CarbonImmutable
    {
        $targetDay = (int) $start->format('j');
        $firstOfTargetMonth = $start->startOfMonth()->addMonths($months);

        if ($targetDay > $firstOfTargetMonth->daysInMonth) {
            return null;
        }

        return $firstOfTargetMonth->day($targetDay)->setTimeFrom($start);
    }
}
