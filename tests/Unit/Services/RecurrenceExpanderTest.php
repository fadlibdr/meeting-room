<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\RecurrenceFrequency;
use App\Services\RecurrenceExpander;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class RecurrenceExpanderTest extends TestCase
{
    private function expander(): RecurrenceExpander
    {
        return new RecurrenceExpander;
    }

    private function start(string $dt = '2026-06-08 10:00:00'): CarbonImmutable
    {
        return CarbonImmutable::parse($dt);
    }

    public function test_daily_by_count_produces_consecutive_days(): void
    {
        $out = $this->expander()->expand(
            $this->start(), $this->start('2026-06-08 11:00:00'),
            RecurrenceFrequency::Daily, 1, null, 3
        );

        $this->assertCount(3, $out);
        $this->assertSame('2026-06-08 10:00', $out[0]['starts_at']->format('Y-m-d H:i'));
        $this->assertSame('2026-06-09 10:00', $out[1]['starts_at']->format('Y-m-d H:i'));
        $this->assertSame('2026-06-10 10:00', $out[2]['starts_at']->format('Y-m-d H:i'));
    }

    public function test_weekly_interval_two(): void
    {
        $out = $this->expander()->expand(
            $this->start(), $this->start('2026-06-08 11:00:00'),
            RecurrenceFrequency::Weekly, 2, null, 3
        );

        $this->assertCount(3, $out);
        $this->assertSame('2026-06-08', $out[0]['starts_at']->format('Y-m-d'));
        $this->assertSame('2026-06-22', $out[1]['starts_at']->format('Y-m-d'));
        $this->assertSame('2026-07-06', $out[2]['starts_at']->format('Y-m-d'));
    }

    public function test_duration_is_preserved(): void
    {
        $out = $this->expander()->expand(
            $this->start('2026-06-08 09:30:00'), $this->start('2026-06-08 11:00:00'),
            RecurrenceFrequency::Daily, 1, null, 2
        );

        foreach ($out as $occ) {
            $this->assertSame(90.0, $occ['starts_at']->diffInMinutes($occ['ends_at']));
        }
    }

    public function test_until_date_bounds_the_series(): void
    {
        $out = $this->expander()->expand(
            $this->start('2026-06-08 10:00:00'), $this->start('2026-06-08 11:00:00'),
            RecurrenceFrequency::Daily, 1, CarbonImmutable::parse('2026-06-10 23:59:59'), null
        );

        $this->assertCount(3, $out); // 8, 9, 10
        $this->assertSame('2026-06-10', end($out)['starts_at']->format('Y-m-d'));
    }

    public function test_monthly_skips_months_without_the_day(): void
    {
        // Jan 31 monthly → Feb has no 31st (skip), Mar 31 exists, Apr has 30 (skip).
        $out = $this->expander()->expand(
            $this->start('2026-01-31 10:00:00'), $this->start('2026-01-31 11:00:00'),
            RecurrenceFrequency::Monthly, 1, CarbonImmutable::parse('2026-05-01'), null
        );

        $dates = array_map(fn ($o) => $o['starts_at']->format('Y-m-d'), $out);
        $this->assertSame(['2026-01-31', '2026-03-31'], $dates);
    }

    public function test_count_is_capped_at_max(): void
    {
        $out = $this->expander()->expand(
            $this->start(), $this->start('2026-06-08 11:00:00'),
            RecurrenceFrequency::Daily, 1, null, 9999
        );

        $this->assertCount(RecurrenceExpander::MAX_OCCURRENCES, $out);
    }
}
