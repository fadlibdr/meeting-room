<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduled report recipients
    |--------------------------------------------------------------------------
    |
    | Permission code whose active holders receive the scheduled email reports.
    |
    */

    'recipient_permission' => 'reports.view',

    /*
    |--------------------------------------------------------------------------
    | BI feed (push)
    |--------------------------------------------------------------------------
    |
    | The scheduled BI export writes a full bookings snapshot (CSV) to this disk
    | and directory for a BI tool to pick up.
    |
    */

    'bi_disk' => env('REPORTS_BI_DISK', 'local_private'),
    'bi_path' => env('REPORTS_BI_PATH', 'bi-exports'),

    /*
    | Disk/dir the generated report XLSX files are stored on (and attached from).
    */
    'report_disk' => env('REPORTS_DISK', 'local_private'),
    'report_path' => env('REPORTS_PATH', 'reports'),

];
