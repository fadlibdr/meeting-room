<?php

use App\Http\Controllers\Auth\AzureSsoController;
use App\Livewire\Actions\Logout;
use App\Livewire\TenantSignup;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::middleware('guest')->group(function () {
    Volt::route('login', 'pages.auth.login')
        ->name('login');

    // Stage 4 4c — self-service tenant onboarding (404 unless tenancy.allow_signup).
    Route::get('signup', TenantSignup::class)
        ->middleware('signup.allowed')
        ->name('tenant.signup');

    // Stage 3 F.1 — Entra ID (Azure AD) SSO. Both 404 while SSO is disabled.
    Route::get('auth/azure/redirect', [AzureSsoController::class, 'redirect'])
        ->name('sso.azure.redirect');
    Route::get('auth/azure/callback', [AzureSsoController::class, 'callback'])
        ->name('sso.azure.callback');

    Volt::route('forgot-password', 'pages.auth.forgot-password')
        ->name('password.request');

    Volt::route('reset-password/{token}', 'pages.auth.reset-password')
        ->name('password.reset');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', function () {
        app(Logout::class)();

        return redirect('/');
    })->name('logout');
});
