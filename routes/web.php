<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\FacilityController;
use App\Http\Controllers\Admin\ParameterExportController;
use App\Http\Controllers\Admin\RoomBlockController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\UnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\ApiDocsController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\BookingAttachmentController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CalendarConnectController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\CheckInController;
use App\Http\Controllers\DataSubjectController;
use App\Http\Controllers\EmailChangeController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\RoomShowController;
use App\Http\Controllers\StatusController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\ForcePasswordChange;
use App\Http\Middleware\TwoFactorGate;
use App\Livewire\Admin\AccessReviewReport;
use App\Livewire\Admin\ApprovalDelegationManager;
use App\Livewire\Admin\ApprovalPolicyManager;
use App\Livewire\Admin\NotificationSettingsManager;
use App\Livewire\Admin\ResourceManager;
use App\Livewire\Admin\RoleManager;
use App\Livewire\Admin\SettingsManager;
use App\Livewire\Admin\UtilizationDashboard;
use App\Livewire\Admin\WebhookSubscriptionManager;
use App\Livewire\ApiTokenManager;
use App\Livewire\Approval\ApprovalInbox;
use App\Livewire\Booking\BookingCalendar;
use App\Livewire\Booking\BookingForm;
use App\Livewire\Booking\BookingList;
use App\Livewire\CalendarSubscription;
use App\Livewire\FrontOffice\DailyCheckIn;
use App\Livewire\NotificationPreferences;
use App\Livewire\Support\ContactForm;
use App\Models\Booking;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return auth()->check() ? redirect()->route('dashboard') : redirect()->route('login');
})->name('welcome');

// Stage 3.1 — UI language switch (guests via session, users persisted to profile).
Route::post('locale/{locale}', [LocaleController::class, 'update'])->name('locale.update');

// Stage 3.2 — PWA offline fallback (cached by the service worker).
Route::view('offline', 'offline')->name('offline');

// Stage 4f.1 — public trust/legal pages (terms, privacy, dpa, security).
Route::get('legal/{doc}', [LegalController::class, 'show'])->name('legal.show');

// Stage 4g.2 — public changelog (rendered from CHANGELOG.md).
Route::get('changelog', [ChangelogController::class, 'show'])->name('changelog');

// Stage 4g.3 — public status page (summarised up/degraded/down only).
Route::get('status', [StatusController::class, 'show'])->name('status');

// Email-change confirmation (link sent to the new address; signed + tokened).
Route::get('email/change/verify/{token}', [EmailChangeController::class, 'verify'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('email.change.verify');

// Telegram bot webhook — public, guarded by the secret path segment (CSRF-exempt).
Route::post('telegram/webhook/{secret}', TelegramWebhookController::class)
    ->middleware('throttle:60,1')
    ->name('telegram.webhook');

// Stage 4h.1 — go-to-market pages (scaffold; 404 unless marketing.enabled).
Route::get('product/{page?}', [MarketingController::class, 'show'])->name('marketing.show');

// Stage 3 A.3 — QR self-check-in (public; the temporary signed URL is the credential).
Route::get('bookings/{booking}/checkin', [CheckInController::class, 'checkIn'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('bookings.checkin');

Route::middleware(['auth', 'user.active', ForcePasswordChange::class, TwoFactorGate::class])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');

    // Forced password change (e.g. the seeded default superadmin's first login).
    Volt::route('password/change-required', 'auth.force-password-change')
        ->name('password.change-required');

    // Two-factor authentication (TOTP) — enrolment + post-login challenge.
    Volt::route('two-factor/setup', 'auth.two-factor-setup')->name('two-factor.setup');
    Volt::route('two-factor/challenge', 'auth.two-factor-challenge')->name('two-factor.challenge');

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
    Route::get('rooms/{room}', RoomShowController::class)->name('rooms.show');

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

        // Configurable RBAC — roles + permission matrix.
        Route::get('roles', RoleManager::class)
            ->middleware('permission:roles.view')
            ->name('roles.index');

        // Parameter exports (CSV) — users, units, rooms, facilities, settings.
        Route::get('export/{entity}', [ParameterExportController::class, 'download'])
            ->name('export');

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

        // UU PDP — admin data-subject actions (export / erasure by anonymisation)
        Route::get('users/{userId}/data-export', [DataSubjectController::class, 'export'])
            ->middleware('permission:users.update')
            ->name('users.data-export');
        Route::post('users/{userId}/anonymize', [DataSubjectController::class, 'anonymize'])
            ->middleware('permission:users.update')
            ->name('users.anonymize');

        // Stage 3 E2b — non-room bookable resources (equipment/vehicles/desks),
        // reuse rooms.* permissions; rooms keep their dedicated admin above.
        Route::get('resources', ResourceManager::class)
            ->middleware('permission:rooms.update')
            ->name('resources.index');

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

        // Compliance Release E — access-review report (CC6.2/6.3 / A.5.18).
        Route::get('reports/access-review', AccessReviewReport::class)
            ->middleware('permission:users.view')
            ->name('reports.access-review');

        // Sprint 5 — Audit-log viewer (read-only; gated activity-logs.view)
        Route::get('logs', [ActivityLogController::class, 'index'])
            ->middleware('permission:activity-logs.view')
            ->name('logs.index');

        // Stage 3 B — approval policy + delegation management
        Route::get('approval-policies', ApprovalPolicyManager::class)
            ->middleware('permission:app-settings.update')
            ->name('approval-policies.index');
        Route::get('approval-delegations', ApprovalDelegationManager::class)
            ->middleware('permission:app-settings.update')
            ->name('approval-delegations.index');

        // Stage 3 C2 — outbound webhook subscriptions
        Route::get('webhooks', WebhookSubscriptionManager::class)
            ->middleware('permission:app-settings.update')
            ->name('webhooks.index');

        // App settings - runtime configuration editor
        Route::get('settings', SettingsManager::class)
            ->middleware('permission:app-settings.view')
            ->name('settings.index');

        // Configurable notifications — admin channel-default matrix.
        Route::get('notifications', NotificationSettingsManager::class)
            ->middleware('permission:app-settings.update')
            ->name('notifications.index');
    });

    Route::view('profile', 'profile')->middleware(['auth'])->name('profile');

    // About page (app info + link to release notes).
    Route::view('about', 'about')->name('about');

    // Stage 3 C — personal API token management
    Route::get('api-tokens', ApiTokenManager::class)->name('api-tokens.index');

    // Stage 4g.1 — in-app support / contact form.
    Route::get('support', ContactForm::class)->name('support');

    // Per-user notification preferences (configurable notifications).
    Route::get('me/notifications', NotificationPreferences::class)->name('notifications.preferences');

    // Stage 3 C — browsable API docs (Redoc over docs/openapi-v1.yaml)
    Route::get('api-docs', [ApiDocsController::class, 'page'])->name('api-docs.page');
    Route::get('api-docs/openapi.yaml', [ApiDocsController::class, 'spec'])->name('api-docs.spec');

    // Stage 3 F.2a — manage the personal calendar (.ics) subscription URL
    Route::get('calendar-subscription', CalendarSubscription::class)->name('calendar-subscription.index');

    // UU PDP — self-service personal data export (right to access)
    Route::get('me/data-export', [DataSubjectController::class, 'exportMine'])->name('data.export.mine');

    // Stage 3 F.2 (activation) — per-user OAuth connect flow for two-way sync
    Route::get('calendar/connect/{provider}', [CalendarConnectController::class, 'redirect'])->name('calendar.connect');
    Route::get('calendar/connect/{provider}/callback', [CalendarConnectController::class, 'callback'])->name('calendar.connect.callback');
    Route::delete('calendar/connect/{provider}', [CalendarConnectController::class, 'disconnect'])->name('calendar.disconnect');
});

// Stage 3 F.2a — public, tokened .ics subscription feed (no session; token is the credential)
Route::get('calendar/feed/{token}.ics', [CalendarFeedController::class, 'feed'])->name('calendar.feed');

require __DIR__.'/auth.php';

// --- Database backup download (app-settings.update: super_admin, system_admin) ---
Route::post('/admin/settings/backup/download', [BackupController::class, 'download'])
    ->middleware(['auth', 'permission:app-settings.update', 'throttle:6,1'])
    ->name('admin.backup.download');
