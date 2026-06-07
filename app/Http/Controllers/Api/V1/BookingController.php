<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\SubmitBookingAction;
use App\Exceptions\ApprovalRoutingException;
use App\Exceptions\BookingConflictException;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\BookingResource;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Public API (v1) — the token owner's bookings (read) + create (booking:write).
 *
 * Create routes through SubmitBookingAction, so conflict detection and the
 * approval chain apply exactly as on the web — the API is not a bypass.
 */
class BookingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $bookings = Booking::query()
            ->where('requester_user_id', $user->id)
            ->with('resource:id,name')
            ->orderByDesc('starts_at')
            ->paginate(25);

        return BookingResource::collection($bookings);
    }

    public function store(Request $request, SubmitBookingAction $submit): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->cannot('create', Booking::class)) {
            abort(403, 'Anda tidak memiliki izin membuat reservasi.');
        }

        // Accept the deprecated `room_id` field as an alias for `resource_id`.
        $request->merge([
            'resource_id' => $request->input('resource_id', $request->input('room_id')),
        ]);

        $validated = $request->validate([
            'resource_id' => ['required', 'integer', Rule::exists('resources', 'id')],
            'subject' => ['required', 'string', 'max:150'],
            'agenda' => ['nullable', 'string', 'max:5000'],
            'attendee_count' => ['required', 'integer', 'min:1'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        try {
            $booking = $submit->execute($user, [
                'resource_id' => (int) $validated['resource_id'],
                'subject' => $validated['subject'],
                'agenda' => $validated['agenda'] ?? null,
                'attendee_count' => (int) $validated['attendee_count'],
                'starts_at' => $validated['starts_at'],
                'ends_at' => $validated['ends_at'],
                'source' => 'api',
            ]);
        } catch (BookingConflictException $e) {
            return response()->json([
                'message' => 'Slot bentrok dengan jadwal lain.',
                'errors' => ['starts_at' => ['Slot tidak tersedia.']],
            ], 422);
        } catch (ApprovalRoutingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new BookingResource($booking->load('resource:id,name')))
            ->response()
            ->setStatusCode(201);
    }
}
