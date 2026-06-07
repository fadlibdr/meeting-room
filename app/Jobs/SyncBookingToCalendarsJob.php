<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Booking;
use App\Services\Calendar\CalendarSyncService;
use App\Support\Tenancy\TenantContext;
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

    public function handle(CalendarSyncService $service, TenantContext $tenant): void
    {
        $booking = Booking::find($this->bookingId);
        if ($booking === null) {
            return;
        }

        // Run within the booking's tenant so connection/event lookups are scoped.
        $tenant->runFor((int) $booking->tenant_id, fn () => $service->sync($booking, $this->action));
    }
}
