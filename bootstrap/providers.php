<?php

use App\Providers\AppServiceProvider;
use App\Providers\BladeDirectivesProvider;
use App\Providers\MailSettingsServiceProvider;
use App\Providers\VoltServiceProvider;

return [
    AppServiceProvider::class,
    BladeDirectivesProvider::class,
    MailSettingsServiceProvider::class,
    VoltServiceProvider::class,
];
