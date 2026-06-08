<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Public demo / sandbox (Stage 4h.2)
    |--------------------------------------------------------------------------
    |
    | HARD-GATED. This is the single most assurance-sensitive feature in 4h: it
    | puts unauthenticated/external users onto the live multi-tenant system.
    |
    | DO NOT enable until the pen-test (esp. tenant isolation) AND the recorded
    | load test are done (D-12/D-8). The /try flow 404s and demo:reset is a
    | no-op while this is false.
    |
    | Enabling also requires the demo to run on its own host/subdomain so
    | ResolveTenant pins the demo tenant for web sessions (web tenant-pinning
    | from the authenticated user is a separate deferred follow-up).
    |
    */
    'enabled' => (bool) env('DEMO_ENABLED', false),

    // The sandbox tenant + auto-login user.
    'tenant_slug' => env('DEMO_TENANT_SLUG', 'demo'),
    'tenant_name' => env('DEMO_TENANT_NAME', 'Demo'),
    'user_email' => env('DEMO_USER_EMAIL', 'demo@demo.invalid'),
    'user_name' => env('DEMO_USER_NAME', 'Pengguna Demo'),

    // Sample data volume seeded on each reset.
    'sample_resources' => (int) env('DEMO_SAMPLE_RESOURCES', 4),

];
