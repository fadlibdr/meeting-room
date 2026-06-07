<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\RoomController;
use App\Http\Middleware\IdentifyTenantFromUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Public API v1 (Stage 3 C). Authenticated by Sanctum personal access tokens;
| every route is rate-limited (throttle:api) and scoped by token ability:
|   - read endpoints require the `read` ability
|   - creating a booking requires `booking:write`
| Writes still route through the domain actions, so conflict + approval rules
| and the caller's own permissions/policies are enforced — the API is not a
| bypass of the web flow.
*/
Route::middleware(['auth:sanctum', IdentifyTenantFromUser::class, 'throttle:api'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::get('user', fn (Request $request) => $request->user()->only(['id', 'name', 'email']))
            ->name('user');

        Route::middleware('ability:read')->group(function (): void {
            Route::get('rooms', [RoomController::class, 'index'])->name('rooms.index');
            Route::get('rooms/{room}/availability', [RoomController::class, 'availability'])->name('rooms.availability');
            Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        });

        Route::middleware('ability:booking:write')
            ->post('bookings', [BookingController::class, 'store'])
            ->name('bookings.store');
    });
