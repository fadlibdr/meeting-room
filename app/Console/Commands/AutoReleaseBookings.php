<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\ReleaseNoShowBookingAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\SettingsService;
use App\Support\Tenancy\RunsPerTenant;
use Illuminate\Console\Command;

/**
 * Stage 3 A.1 — releases ongoing no-show bookings.
 *
 * Eligibility (deliberately bounded to LIVE meetings):
 *   - status Approved
 *   - started more than `booking.auto_release_grace_minutes` ago
 *   - has not ended yet (now < ends_at)
 *   - never checked in (checked_in_at IS NULL)
 *   - not already released (released_at IS NULL — idempotent)
 *
 * Bounding to in-progress meetings reclaims a wasted room now and, critically,
 * avoids a retroactive mass-cancel/email blast over historical no-shows on the
 * first run. Scheduled every 5 minutes in routes/console.php.
 */
class AutoReleaseBookings extends Command
{
    use RunsPerTenant;

    protected $signature = 'bookings:auto-release';

    protected $description = 'Auto-release approved bookings whose attendees never checked in (ongoing no-shows).';

    private const DEFAULT_GRACE_MINUTES = 15;

    public function handle(SettingsService $settings, ReleaseNoShowBookingAction $action): int
    {
        $this->eachTenant(function () use ($settings, $action): void {
            $this->releaseForCurrentTenant($settings, $action);
        });

        return self::SUCCESS;
    }

    private function releaseForCurrentTenant(SettingsService $settings, ReleaseNoShowBookingAction $action): void
    {
        if (! (bool) $settings->get('booking.auto_release_enabled', true)) {
            $this->info('Auto-release disabled — skipped.');

            return;
        }

        $grace = (int) $settings->get('booking.auto_release_grace_minutes', self::DEFAULT_GRACE_MINUTES);
        $grace = max(0, $grace);

        $now = now();
        $cutoff = $now->copy()->subMinutes($grace);

        $bookings = Booking::query()
            ->where('status', BookingStatus::Approved)
            ->whereNull('checked_in_at')
            ->whereNull('released_at')
            ->where('starts_at', '<', $cutoff) // started more than `grace` ago
            ->where('ends_at', '>', $now)      // still ongoing (not ended)
            ->get();

        $released = 0;
        foreach ($bookings as $booking) {
            if ($action->execute($booking)->isAutoReleased()) {
                $released++;
            }
        }

        $this->info("Auto-released {$released} no-show booking(s).");
    }
}
