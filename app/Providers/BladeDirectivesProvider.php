<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BladeDirectivesProvider extends ServiceProvider
{
    public function boot(): void
    {
        /**
         * @displayDateTime($value)
         * Per Dec-09: DB stores UTC, UI displays in user timezone or APP_DISPLAY_TIMEZONE.
         */
        Blade::directive('displayDateTime', function ($expression) {
            return "<?php
                \$__dt = {$expression};
                if (\$__dt) {
                    \$__tz = auth()->check() && auth()->user()->timezone
                        ? auth()->user()->timezone
                        : config('app.display_timezone', 'Asia/Jakarta');
                    echo \Carbon\Carbon::parse(\$__dt)
                        ->setTimezone(\$__tz)
                        ->format('d M Y, H:i')
                        . ' ' . (\$__tz === 'Asia/Jakarta' ? 'WIB' : \$__tz);
                }
            ?>";
        });

        /**
         * @displayDate($value)
         */
        Blade::directive('displayDate', function ($expression) {
            return "<?php
                \$__d = {$expression};
                if (\$__d) {
                    \$__tz = auth()->check() && auth()->user()->timezone
                        ? auth()->user()->timezone
                        : config('app.display_timezone', 'Asia/Jakarta');
                    echo \Carbon\Carbon::parse(\$__d)
                        ->setTimezone(\$__tz)
                        ->format('d M Y');
                }
            ?>";
        });

        /**
         * @hasPermission('bookings.approve')
         * Placeholder — actual implementation depends on RBAC (Sprint 1).
         */
        Blade::if('hasPermission', function (string $permission) {
            if (! auth()->check()) {
                return false;
            }

            // TODO Sprint 1: wire to PermissionCacheService.
            return auth()->user()->hasPermission($permission);
        });
    }
}
