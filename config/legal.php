<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Legal review flags (Stage 4f.1)
    |--------------------------------------------------------------------------
    |
    | Each public legal/trust document ships as a UU PDP (UU 27/2022) + GDPR
    | -aware OUTLINE authored by engineering — NOT legal advice. Until counsel
    | drafts and approves the binding text, the document renders with a
    | "draft — pending legal review" banner.
    |
    | Flip a flag to true ONLY after counsel has approved that document's text.
    | These are read by LegalController to decide whether to show the banner.
    |
    */
    'reviewed' => [
        'terms' => (bool) env('LEGAL_REVIEWED_TERMS', false),
        'privacy' => (bool) env('LEGAL_REVIEWED_PRIVACY', false),
        'dpa' => (bool) env('LEGAL_REVIEWED_DPA', false),
        'security' => (bool) env('LEGAL_REVIEWED_SECURITY', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Document registry
    |--------------------------------------------------------------------------
    |
    | The allowed {doc} slugs and their display titles. Anything not listed
    | here 404s. Markdown source lives at resources/legal/<slug>.md so legal
    | and ops can edit copy without a code change.
    |
    */
    'documents' => [
        'terms' => 'Syarat & Ketentuan',
        'privacy' => 'Kebijakan Privasi',
        'dpa' => 'Perjanjian Pemrosesan Data (DPA)',
        'security' => 'Keamanan',
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact for data-protection / legal enquiries
    |--------------------------------------------------------------------------
    */
    'contact' => [
        'controller' => env('LEGAL_CONTROLLER_NAME', 'BPJS Kesehatan'),
        'dpo_email' => env('LEGAL_DPO_EMAIL', 'dpo@bpjs-kesehatan.go.id'),
    ],

];
