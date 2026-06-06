<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Booking;
use Illuminate\Support\Facades\URL;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Stage 3 A.3 — builds the signed QR self-check-in link for a booking.
 *
 * The URL is a temporary signed route that expires at the meeting's end, so it
 * cannot be replayed after the booking is over (a tampered or expired link is
 * rejected by the `signed` middleware). The controller additionally enforces the
 * check-in window. Rendered as an inline SVG QR on the booking page and the
 * front-office screen.
 */
final class BookingCheckInLink
{
    public function signedUrl(Booking $booking): string
    {
        return URL::temporarySignedRoute(
            'bookings.checkin',
            $booking->ends_at,
            ['booking' => $booking->id],
        );
    }

    public function qrSvg(Booking $booking, int $size = 180): string
    {
        return (string) QrCode::format('svg')
            ->size($size)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($this->signedUrl($booking));
    }
}
