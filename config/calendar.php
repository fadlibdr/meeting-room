<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Two-way calendar sync (Stage 3 F.2b/c — Microsoft Graph / Google)
    |--------------------------------------------------------------------------
    |
    | OFF by default. This pushes booking lifecycle changes (approved/updated/
    | cancelled) into users' Outlook/Google calendars as real events. It needs
    | a configured Entra/GCP app and (for application mode) admin consent; until
    | then leave CALENDAR_SYNC_ENABLED=false.
    |
    | consent_mode:
    |   'delegated'   — write to a user's calendar using THEIR stored OAuth
    |                   token (calendar_connections row); per-user connect flow.
    |   'application' — one app credential writes to any mailbox (Graph app
    |                   perms / Google domain-wide delegation) via admin consent.
    */

    'sync' => [
        'enabled' => (bool) env('CALENDAR_SYNC_ENABLED', false),
        'consent_mode' => env('CALENDAR_CONSENT_MODE', 'delegated'), // delegated | application
    ],

    'microsoft' => [
        'enabled' => (bool) env('CALENDAR_MS_ENABLED', false),
        'tenant' => env('AZURE_TENANT_ID'),
        'client_id' => env('AZURE_CLIENT_ID'),
        'client_secret' => env('AZURE_CLIENT_SECRET'),
        'graph_base' => env('CALENDAR_MS_GRAPH_BASE', 'https://graph.microsoft.com/v1.0'),
    ],

    'google' => [
        'enabled' => (bool) env('CALENDAR_GOOGLE_ENABLED', false),
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'api_base' => env('CALENDAR_GOOGLE_API_BASE', 'https://www.googleapis.com/calendar/v3'),
    ],

];
