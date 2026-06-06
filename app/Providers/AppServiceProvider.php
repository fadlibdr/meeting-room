<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Public API (Stage 3 C) — 60 req/min per token (fallback to IP).
        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->user()?->id ? 'u'.$request->user()->id : (string) $request->ip()));
    }
}
