<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Contracts\View\View;

/**
 * Public room detail page (clickable from the /rooms cards).
 */
class RoomShowController extends Controller
{
    public function __invoke(Room $room): View
    {
        abort_unless($room->status->value === 'active', 404);

        $room->load(['facilityItems.facility', 'operatingHours']);

        return view('rooms.show', ['room' => $room]);
    }
}
