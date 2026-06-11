<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\View\View;

class RoomController extends Controller
{
    /** Room management list (Livewire-hosted). Route gated by rooms.update. */
    public function index(): View
    {
        return view('admin.rooms.index');
    }

    public function create(): View
    {
        return view('admin.rooms.create');
    }

    public function edit(string $roomId): View
    {
        return view('admin.rooms.edit', ['roomId' => Room::decodeHashidOrFail($roomId)]);
    }
}
