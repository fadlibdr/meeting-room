<?php

use App\Providers\AppServiceProvider;
use App\Providers\BladeDirectivesProvider;
use App\Providers\MailSettingsServiceProvider;
use App\Providers\RuntimeSettingsServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    BladeDirectivesProvider::class,
    MailSettingsServiceProvider::class,
    RuntimeSettingsServiceProvider::class,
    VoltServiceProvider::class,
];
