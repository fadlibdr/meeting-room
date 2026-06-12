<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Content-Security-Policy enforcement
    |--------------------------------------------------------------------------
    |
    | When true, the CSP is sent as the enforcing `Content-Security-Policy`
    | header; when false it falls back to `Content-Security-Policy-Report-Only`
    | (violations are reported but not blocked). This is an env-toggleable kill
    | switch: if the enforcing policy ever breaks a page on a live host, set
    | SECURITY_CSP_ENFORCE=false and `php artisan config:cache` to roll back
    | instantly without a code deploy.
    |
    */

    'csp_enforce' => env('SECURITY_CSP_ENFORCE', true),

];
