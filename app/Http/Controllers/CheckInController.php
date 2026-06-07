<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CheckInBookingAction;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Http\Response;

/**
 * Stage 3 A.3 — QR self-check-in.
 *
 * Reached via a temporary signed URL (the `signed` middleware rejects tampered
 * or expired links with a 403). Possession of the valid signed link is the
 * credential, so no login is required; the controller additionally enforces the
 * check-in window and booking state, then stamps the check-in through the shared
 * CheckInBookingAction — which in turn makes the booking ineligible for no-show
 * auto-release (A.1). Renders a standalone confirmation page.
 */
class CheckInController extends Controller
{
    /** Earliest check-in: this many minutes before the meeting starts. */
    public const LEAD_MINUTES = 30;

    public function checkIn(int $booking, CheckInBookingAction $action, TenantContext $tenant): Response
    {
        // The signed URL is the credential — resolve the booking across tenants
        // (it's keyed by id, not host), then pin the context for the check-in.
        $model = Booking::query()->withoutGlobalScope('tenant')->find($booking);
        abort_if($model === null, 404);

        return $tenant->runFor((int) $model->tenant_id, function () use ($model, $action): Response {
            $status = $this->evaluate($model);
            $http = 200;

            if ($status === 'ok') {
                $action->execute($model, null); // null actor = QR self-service
                $status = 'success';
            } elseif (in_array($status, ['too_early', 'too_late', 'ineligible'], true)) {
                $http = 422;
            }

            return response()->view('bookings.checkin-result', [
                'booking' => $model->fresh(['room']) ?? $model,
                'status' => $status,
            ], $http);
        });
    }

    private function evaluate(Booking $booking): string
    {
        if ($booking->checked_in_at !== null) {
            return 'already';
        }

        if ($booking->released_at !== null || $booking->status !== BookingStatus::Approved) {
            return 'ineligible';
        }

        $now = CarbonImmutable::now();

        if ($now->lt($booking->starts_at->toImmutable()->subMinutes(self::LEAD_MINUTES))) {
            return 'too_early';
        }

        if ($now->gt($booking->ends_at->toImmutable())) {
            return 'too_late';
        }

        return 'ok';
    }
}
