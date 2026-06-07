<?php

declare(strict_types=1);

return [
    /*
    | Stage 4a P0 spike — row-level multi-tenancy, OFF by default.
    |
    | While disabled, BelongsToTenant is a complete no-op (no global scope, no
    | tenant_id stamping), so the live single-tenant app is byte-identical. The
    | spike covers ONE vertical (resources + bookings); full rollout is P1+.
    */
    'enabled' => (bool) env('TENANCY_ENABLED', false),
];
