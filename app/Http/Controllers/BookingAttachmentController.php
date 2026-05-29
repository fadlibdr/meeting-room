<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingAttachment;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
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

    /**
     * Store an uploaded attachment on the private disk and record it.
     *
     * Gated by can('manageAttachments', 'booking') on the route.
     */
    public function store(Request $request, Booking $booking, ActivityLogger $logger): RedirectResponse
    {
        $request->validate([
            'attachment' => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,png,jpg,jpeg'],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('attachment');

        $path = $file->store('booking-attachments/'.$booking->id, 'local_private');

        if ($path === false) {
            return back()->withErrors(['attachment' => 'Gagal menyimpan lampiran.']);
        }

        /** @var User $user */
        $user = $request->user();

        $attachment = BookingAttachment::create([
            'booking_id' => $booking->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'disk' => 'local_private',
            'path' => $path,
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => (int) $file->getSize(),
            'uploaded_by_user_id' => $user->id,
        ]);

        $logger->log('bookings', 'attach', $booking, [
            'description' => sprintf('%s melampirkan "%s" pada booking %s.', $user->name, $attachment->original_name, $booking->booking_code),
            'context' => [
                'attachment_id' => $attachment->id,
                'original_name' => $attachment->original_name,
                'size_bytes' => $attachment->size_bytes,
            ],
        ]);

        return redirect()
            ->route('bookings.show', $booking->id)
            ->with('status', 'Lampiran berhasil diunggah.');
    }

    /**
     * Delete an attachment (file + record).
     *
     * Gated by can('manageAttachments', 'booking') on the route, with the
     * {attachment} binding scoped to {booking}.
     */
    public function destroy(Booking $booking, BookingAttachment $attachment, ActivityLogger $logger): RedirectResponse
    {
        Storage::disk($attachment->disk)->delete($attachment->path);

        $originalName = $attachment->original_name;
        $attachment->delete();

        $logger->log('bookings', 'detach', $booking, [
            'description' => sprintf('Lampiran "%s" dihapus dari booking %s.', $originalName, $booking->booking_code),
            'context' => [
                'original_name' => $originalName,
            ],
        ]);

        return redirect()
            ->route('bookings.show', $booking->id)
            ->with('status', 'Lampiran dihapus.');
    }
}
