<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Marketing / go-to-market pages (Stage 4h.1)
    |--------------------------------------------------------------------------
    |
    | The marketing surface is SCAFFOLDED but not launched. It stays behind
    | `enabled` (default off) until business sign-off — structure now, copy and
    | launch later. The whole /product surface 404s while disabled.
    |
    */
    'enabled' => (bool) env('MARKETING_ENABLED', false),

    /*
    | Pricing is gated SEPARATELY and stays off even when marketing is enabled.
    | Per the brief: do NOT publish pricing implying a commercial offering until
    | 4d billing + 4f legal + the security review exist.
    */
    'pricing_enabled' => (bool) env('MARKETING_PRICING_ENABLED', false),

    // The allowed {page} slugs and their titles. Markdown lives at
    // resources/marketing/<slug>.md.
    'pages' => [
        'landing' => 'Sistem Pemesanan Ruang Rapat',
        'features' => 'Fitur',
        'pricing' => 'Harga',
    ],

];
