<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Support inbox (Stage 4g.1)
    |--------------------------------------------------------------------------
    |
    | Where in-app "Bantuan / Hubungi" requests are emailed. Falls back to the
    | mail "from" address when unset, so support never silently goes nowhere.
    |
    */
    'to' => env('SUPPORT_EMAIL') ?: env('MAIL_FROM_ADDRESS'),

    // Optional external help-centre URL surfaced on the support page.
    'help_center_url' => env('SUPPORT_HELP_CENTER_URL'),

];
