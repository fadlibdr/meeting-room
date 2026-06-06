<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\WebhookEvent;
use App\Models\Booking;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\WebhookDispatcher;

/**
 * Stamps a booking's check-in (Stage 4.1 front-office + Stage 3 A.3 QR).
 *
 * The single check-in path, shared by the front-office daily view and the QR
 * self-check-in controller. Idempotent — an existing `checked_in_at` is never
 * overwritten — and a check-in makes the booking ineligible for the no-show
 * auto-release (A.1). The actor is the front-office user at the desk, or null
 * for a QR self-check-in (the signed URL is the credential).
 */
final class CheckInBookingAction
{
    public function __construct(
        private readonly ActivityLogger $logger,
    ) {}

    public function execute(Booking $booking, ?User $actor = null): Booking
    {
        if ($booking->checked_in_at === null) {
            $booking->forceFill(['checked_in_at' => now()])->save();

            $this->logger->log('bookings', 'check-in', $booking, [
                'description' => sprintf('Check-in tamu untuk reservasi %s.', $booking->booking_code),
                'context' => [
                    'booking_code' => $booking->booking_code,
                    'via' => $actor !== null ? 'desk' : 'qr',
                ],
            ], $actor);

            app(WebhookDispatcher::class)->dispatch(WebhookEvent::BookingCheckedIn, $booking);
        }

        return $booking;
    }
}
