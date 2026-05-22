<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BookingController;
use App\Livewire\Admin\SettingsManager;
use App\Livewire\Approval\ApprovalInbox;
use App\Livewire\Booking\BookingCalendar;
use App\Livewire\Booking\BookingForm;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('welcome');

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Placeholders for Sprint 2/3 — will be replaced
    Route::view('bookings', 'placeholder')->name('bookings.index');
    Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('bookings/new', BookingForm::class)
        ->middleware('permission:bookings.create')
        ->name('bookings.new');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])
        ->can('view', 'booking')
        ->name('bookings.show');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->name('bookings.cancel');
    Route::get('calendar', BookingCalendar::class)
        ->middleware('permission:bookings.view')
        ->name('calendar.index');
    Route::get('approvals', ApprovalInbox::class)
        ->middleware('permission:bookings.approve')
        ->name('approvals.index');
    Route::view('rooms', 'placeholder')->name('rooms.index');

    // Placeholders for admin routes (1D replaces users)
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('users.index');

        Route::get('users/create', [UserController::class, 'create'])
            ->middleware('permission:users.create')
            ->name('users.create');

        Route::get('users/{userId}/edit', [UserController::class, 'edit'])
            ->middleware('permission:users.update')
            ->name('users.edit');

        // Placeholders for Sprint 2/3
        Route::view('rooms', 'placeholder')->name('rooms.index');
        Route::view('logs', 'placeholder')->name('logs.index');

        // App settings - runtime configuration editor
        Route::get('settings', SettingsManager::class)
            ->middleware('permission:app-settings.view')
            ->name('settings.index');
    });

    Route::view('profile', 'profile')->middleware(['auth'])->name('profile');
});

require __DIR__.'/auth.php';
