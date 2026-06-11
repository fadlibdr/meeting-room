<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\View\View;

class UserController extends Controller
{
    /**
     * Display the user management list.
     */
    public function index(): View
    {
        return view('admin.users.index');
    }

    /**
     * Display the form to create a new user.
     */
    public function create(): View
    {
        return view('admin.users.create');
    }

    /**
     * Display the form to edit an existing user.
     */
    public function edit(string $userId): View
    {
        return view('admin.users.edit', ['userId' => User::decodeHashidOrFail($userId)]);
    }
}
