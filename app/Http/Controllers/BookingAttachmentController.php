<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingAttachment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingAttachmentController extends Controller
{
    /**
     * Stream a booking attachment as a download.
     *
     * The route gates access with can('view', 'booking') and scopes the
     * {attachment} binding to {booking}, so an unauthorized user (403) or an
     * attachment that doesn't belong to the booking (404) never reaches here.
     */
    public function download(Booking $booking, BookingAttachment $attachment): StreamedResponse
    {
        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }
}
