<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class RoomBlockController extends Controller
{
    public function index(): View
    {
        return view('admin.room-blocks.index');
    }

    public function create(): View
    {
        return view('admin.room-blocks.create');
    }
}
