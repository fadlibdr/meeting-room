<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('welcome');

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Placeholders for Sprint 2/3 — will be replaced
    Route::view('bookings', 'placeholder')->name('bookings.index');
    Route::view('approvals', 'placeholder')->name('approvals.index');
    Route::view('rooms', 'placeholder')->name('rooms.index');

    // Placeholders for admin routes (1D replaces users)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::view('users', 'placeholder')->name('users.index');
        Route::view('rooms', 'placeholder')->name('rooms.index');
        Route::view('logs', 'placeholder')->name('logs.index');
    });

    Route::view('profile', 'profile')->middleware(['auth'])->name('profile');
});

require __DIR__.'/auth.php';
