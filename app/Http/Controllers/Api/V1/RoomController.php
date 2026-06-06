<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\DataTransferObjects\ConflictItem;
use App\Enums\RoomStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RoomResource;
use App\Models\Room;
use App\Services\BookingConflictService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Public API (v1) — rooms + availability. Read scope.
 */
class RoomController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $rooms = Room::query()
            ->where('status', RoomStatus::Active)
            ->orderBy('name')
            ->paginate(50);

        return RoomResource::collection($rooms);
    }

    public function availability(Request $request, Room $room, BookingConflictService $conflicts): JsonResponse
    {
        $validated = $request->validate([
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $startsAt = CarbonImmutable::parse($validated['starts_at'])->utc();
        $endsAt = CarbonImmutable::parse($validated['ends_at'])->utc();

        $found = $conflicts->findConflicts($room, $startsAt, $endsAt);

        return response()->json([
            'data' => [
                'room_id' => $room->id,
                'starts_at' => $startsAt->toIso8601String(),
                'ends_at' => $endsAt->toIso8601String(),
                'available' => $found->isEmpty(),
                'conflicts' => $found->map(fn (ConflictItem $c): array => [
                    'type' => $c->type,
                    'title' => $c->title,
                ])->values(),
            ],
        ]);
    }
}
