<?php

declare(strict_types=1);

namespace App\Services\Calendar;

use App\Jobs\SyncBookingToCalendarsJob;
use App\Models\Booking;

/**
 * Stage 3 F.2b/c — queues a calendar sync after a booking lifecycle change.
 * Mirrors WebhookDispatcher: called from the actions' post-commit section.
 */
class CalendarSyncDispatcher
{
    public function dispatch(Booking $booking, string $action): void
    {
        if (! config('calendar.sync.enabled')) {
            return;
        }

        SyncBookingToCalendarsJob::dispatch($booking->id, $action)->afterCommit();
    }
}
