<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Data retention enforcement (Stage 4f.3)
    |--------------------------------------------------------------------------
    |
    | The `data:enforce-retention` command anonymises personal data that has
    | aged past its retention window. It is DRY-RUN by default; pass --execute
    | to actually act. Anonymisation (not hard-delete) is used so booking /
    | approval / audit history stays referentially intact.
    |
    */

    // Per-category retention windows, in days. A category with `enabled => false`
    // is skipped entirely.
    'categories' => [

        // Users who were deactivated and have sat inactive longer than this
        // window have their identifying fields scrubbed (AnonymizeUserAction).
        'inactive_users' => [
            'enabled' => (bool) env('RETENTION_INACTIVE_USERS_ENABLED', true),
            'days' => (int) env('RETENTION_INACTIVE_USERS_DAYS', 1095), // ~3 years
        ],

    ],

    /*
    | Bounded-window guard (mirrors the auto-release "don't mass-act on first
    | run" lesson). In --execute mode, if a category has more eligible records
    | than this, the command REFUSES to act on that category and reports the
    | count, unless --force-bulk is given. This prevents a misconfigured (too
    | small) window from anonymising everyone on the first scheduled run.
    */
    'max_per_run' => (int) env('RETENTION_MAX_PER_RUN', 50),

];
