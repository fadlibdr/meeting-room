<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\RoomBlockController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BookingAttachmentController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LocaleController;
use App\Livewire\Admin\SettingsManager;
use App\Livewire\Admin\UtilizationDashboard;
use App\Livewire\Approval\ApprovalInbox;
use App\Livewire\Booking\BookingCalendar;
use App\Livewire\Booking\BookingForm;
use App\Livewire\Booking\BookingList;
use App\Livewire\FrontOffice\DailyCheckIn;
use App\Models\Booking;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('welcome');

// Stage 3.1 — UI language switch (guests via session, users persisted to profile).
Route::post('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

// Stage 3.2 — PWA offline fallback (cached by the service worker).
Route::view('offline', 'offline')->name('offline');

Route::middleware(['auth', 'user.active'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Placeholders for Sprint 2/3 — will be replaced
    Route::get('bookings', BookingList::class)
        ->can('viewAny', Booking::class)
        ->name('bookings.index');
    Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('bookings/new', BookingForm::class)
        ->middleware('permission:bookings.create')
        ->name('bookings.new');
    Route::get('bookings/{booking}', [BookingController::class, 'show'])
        ->can('view', 'booking')
        ->name('bookings.show');
    Route::get('bookings/{booking}/calendar.ics', [BookingController::class, 'calendar'])
        ->can('view', 'booking')
        ->name('bookings.calendar');
    Route::get('bookings/{booking}/attachments/{attachment}', [BookingAttachmentController::class, 'download'])
        ->scopeBindings()
        ->can('view', 'booking')
        ->name('bookings.attachments.download');
    Route::post('bookings/{booking}/attachments', [BookingAttachmentController::class, 'store'])
        ->can('manageAttachments', 'booking')
        ->name('bookings.attachments.store');
    Route::delete('bookings/{booking}/attachments/{attachment}', [BookingAttachmentController::class, 'destroy'])
        ->scopeBindings()
        ->can('manageAttachments', 'booking')
        ->name('bookings.attachments.destroy');
    Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])
        ->name('bookings.cancel');
    Route::post('bookings/{booking}/cancel-series', [BookingController::class, 'cancelSeries'])
        ->can('cancel', 'booking')
        ->name('bookings.cancel-series');
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

    // Stage 4.1 — front-office daily view + manual check-in
    Route::get('front-desk', DailyCheckIn::class)
        ->middleware('permission:bookings.check-in')
        ->name('front-office.index');
    Route::view('rooms', 'rooms.public')->name('rooms.index');

    // Stage 2.2 — download a queued data export (owner-only; enforced in controller)
    Route::get('exports/{export}/download', [ExportController::class, 'download'])
        ->name('exports.download');

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

        // Org structure — Units (reuse users.* permissions, like Facilities reuse rooms.*)
        Route::get('units', [UnitController::class, 'index'])
            ->middleware('permission:users.view')
            ->name('units.index');

        Route::get('units/create', [UnitController::class, 'create'])
            ->middleware('permission:users.create')
            ->name('units.create');

        Route::get('units/{unitId}/edit', [UnitController::class, 'edit'])
            ->middleware('permission:users.update')
            ->name('units.edit');

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

        // Sprint 2 — Facilities (reuse rooms.* permissions — Dec-19)
        Route::get('facilities', [FacilityController::class, 'index'])
            ->middleware('permission:rooms.update')
            ->name('facilities.index');
        Route::get('facilities/create', [FacilityController::class, 'create'])
            ->middleware('permission:rooms.create')
            ->name('facilities.create');
        Route::get('facilities/{facilityId}/edit', [FacilityController::class, 'edit'])
            ->middleware('permission:rooms.update')
            ->name('facilities.edit');

        // Sprint 2 — Room blocking (gated rooms.manage-blocks — Dec-17 / §G)
        Route::get('room-blocks', [RoomBlockController::class, 'index'])
            ->middleware('permission:rooms.manage-blocks')
            ->name('room-blocks.index');
        Route::get('room-blocks/create', [RoomBlockController::class, 'create'])
            ->middleware('permission:rooms.manage-blocks')
            ->name('room-blocks.create');

        // Stage 2.1d — Room utilization analytics (read-only; gated reports.view)
        Route::get('reports/utilization', UtilizationDashboard::class)
            ->middleware('permission:reports.view')
            ->name('reports.utilization');

        // Sprint 5 — Audit-log viewer (read-only; gated activity-logs.view)
        Route::get('logs', [ActivityLogController::class, 'index'])
            ->middleware('permission:activity-logs.view')
            ->name('logs.index');

        // App settings - runtime configuration editor
        Route::get('settings', SettingsManager::class)
            ->middleware('permission:app-settings.view')
            ->name('settings.index');
    });

    Route::view('profile', 'profile')->middleware(['auth'])->name('profile');
});

require __DIR__.'/auth.php';

// --- Database backup download (app-settings.update: super_admin, system_admin) ---
Route::post('/admin/settings/backup/download', [BackupController::class, 'download'])
    ->middleware(['auth', 'permission:app-settings.update', 'throttle:6,1'])
    ->name('admin.backup.download');
