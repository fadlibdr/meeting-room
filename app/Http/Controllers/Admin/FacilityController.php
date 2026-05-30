<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function index(): View
    {
        return view('admin.facilities.index');
    }

    public function create(): View
    {
        return view('admin.facilities.create');
    }

    public function edit(int $facilityId): View
    {
        return view('admin.facilities.edit', ['facilityId' => $facilityId]);
    }
}
