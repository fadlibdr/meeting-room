<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Booking;
use App\Services\Calendar\CalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Stage 3 F.2b/c — pushes a booking's calendar sync off the request cycle.
 */
class SyncBookingToCalendarsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $bookingId,
        public string $action,
    ) {}

    public function handle(CalendarSyncService $service): void
    {
        $booking = Booking::find($this->bookingId);
        if ($booking !== null) {
            $service->sync($booking, $this->action);
        }
    }
}
