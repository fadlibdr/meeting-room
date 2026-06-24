<?php

namespace App\Providers;

use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Azure\AzureExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Stage 4a — one tenant context per request/job lifecycle.
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public API (Stage 3 C) — 60 req/min per token (fallback to IP).
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->id ? 'u'.$request->user()->id : (string) $request->ip()));

        // Stage 3 F.1 — register the Entra ID (Azure AD) Socialite provider.
        Event::listen(SocialiteWasCalled::class, [AzureExtendSocialite::class, 'handle']);

        // Security audit trail (SOC 2 CC7.2 / ISO 27001 A.8.15): the auth-event
        // handlers in App\Listeners\SecurityEventSubscriber are auto-registered by
        // Laravel 11's listener discovery (app/Listeners is scanned), so no manual
        // registration is needed here — doing so would double-fire each event.
    }
}
