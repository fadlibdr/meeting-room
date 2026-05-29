<?php

use App\Http\Controllers\Admin\RoomController;
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
    Route::post('bookings/{booking}/submit', [BookingController::class, 'submit'])
        ->can('submit', 'booking')
        ->name('bookings.submit');
    Route::get('bookings/{booking}/edit', BookingForm::class)
        ->can('update', 'booking')
        ->name('bookings.edit');
    Route::get('bookings/{booking}/reschedule', BookingForm::class)
        ->can('reschedule', 'booking')
        ->name('bookings.reschedule');
    Route::delete('bookings/{booking}', [BookingController::class, 'destroy'])
        ->can('delete', 'booking')
        ->name('bookings.destroy');
    Route::get('calendar', BookingCalendar::class)
        ->middleware('permission:bookings.view')
        ->name('calendar.index');
    Route::get('approvals', ApprovalInbox::class)
        ->middleware('permission:bookings.approve')
        ->name('approvals.index');
    Route::view('rooms', 'placeholder')->name('rooms.index');

    // Admin
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

        // Sprint 2 — Room Management (management UI gated by rooms.update;
        // view-only roles hold rooms.view but use the public list instead)
        Route::get('rooms', [RoomController::class, 'index'])
            ->middleware('permission:rooms.update')
            ->name('rooms.index');
        Route::get('rooms/create', [RoomController::class, 'create'])
            ->middleware('permission:rooms.create')
            ->name('rooms.create');
        Route::get('rooms/{roomId}/edit', [RoomController::class, 'edit'])
            ->middleware('permission:rooms.update')
            ->name('rooms.edit');

        Route::view('logs', 'placeholder')->name('logs.index');

        // App settings - runtime configuration editor
        Route::get('settings', SettingsManager::class)
            ->middleware('permission:app-settings.view')
            ->name('settings.index');
    });

    Route::view('profile', 'profile')->middleware(['auth'])->name('profile');
});

require __DIR__.'/auth.php';
