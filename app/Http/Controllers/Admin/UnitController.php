<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(): View
    {
        return view('admin.units.index');
    }

    public function create(): View
    {
        return view('admin.units.create');
    }

    public function edit(int $unitId): View
    {
        return view('admin.units.edit', ['unitId' => $unitId]);
    }
}
